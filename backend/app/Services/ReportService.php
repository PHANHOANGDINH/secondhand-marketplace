<?php

namespace App\Services;

use App\Enums\ProductStatus;
use App\Enums\ReportStatus;
use App\Enums\ReportTargetType;
use App\Enums\VideoCommentStatus;
use App\Events\NotificationCreated;
use App\Http\Resources\NotificationResource;
use App\Models\Product;
use App\Models\Report;
use App\Models\Shop;
use App\Models\User;
use App\Models\VideoComment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ReportService
{
    public function __construct(private ProductQuery $products, private ShopService $shops, private VideoCommentService $comments, private AuditService $audit) {}

    public function publicTargets(ReportTargetType $type): Builder
    {
        return match ($type) {
            ReportTargetType::Product => $this->products->publicVisible(),
            ReportTargetType::Shop => $this->shops->publicQuery(),
            ReportTargetType::VideoComment => VideoComment::where('status', VideoCommentStatus::Published)->whereIn('product_video_id', $this->comments->publicVideos()->select('id')),
        };
    }

    public function create(User $user, array $data): Report
    {
        return DB::transaction(function () use ($user, $data): Report {
            $actor = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('create', Report::class);
            $type = ReportTargetType::from($data['target_type']);
            $target = $this->publicTargets($type)->whereKey($data['target_id'])->lockForUpdate()->firstOrFail();
            abort_if(Report::where('reporter_user_id', $actor->id)->where('target_type', $type)->where('target_id', $target->id)->whereIn('status', ['PENDING', 'IN_REVIEW'])->exists(), 409, 'Bạn đã có báo cáo đang được xem xét cho nội dung này.');
            $report = new Report;
            $report->forceFill(['reporter_user_id' => $actor->id, 'target_type' => $type, 'target_id' => $target->id, 'target_name' => $type === ReportTargetType::VideoComment ? 'Bình luận video #'.$target->id : mb_substr($target->name, 0, 255), 'reason' => $data['reason'], 'description' => $data['description'] ?? null, 'status' => ReportStatus::Pending])->save();
            if ($type === ReportTargetType::Product) {
                $this->hideWhenReportedEnough($actor, $target);
            }

            return $report;
        }, 3);
    }

    public function moderate(User $user, Report $report, array $data): Report
    {
        return DB::transaction(function () use ($user, $report, $data): Report {
            $actor = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $locked = Report::whereKey($report->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('moderate', $locked);
            if ($locked->status->value === $data['status']) {
                // A repeated transition (stale expected_status) stays an idempotent no-op; only a request that
                // names the current status as both expected and target is a deliberate note edit.
                $note = $data['admin_note'] ?? null;
                if ($data['expected_status'] === $data['status'] && $note !== $locked->admin_note) {
                    $locked->forceFill(['admin_note' => $note])->save();
                    $this->audit->record($actor, 'reports', $locked->id, 'report.note_updated', $locked->status->value, $locked->status->value, null);
                }

                return $locked;
            }
            $allowed = ['PENDING' => ['IN_REVIEW', 'DISMISSED'], 'IN_REVIEW' => ['RESOLVED', 'DISMISSED'], 'RESOLVED' => [], 'DISMISSED' => []];
            $from = $locked->status->value;
            abort_unless($from === $data['expected_status'] && in_array($data['status'], $allowed[$from], true), 409, 'Trạng thái đã thay đổi hoặc bước xử lý không hợp lệ. Vui lòng tải lại.');
            $locked->forceFill(['status' => $data['status'], 'handled_by' => $actor->id, 'handled_at' => now(), 'admin_note' => $data['admin_note'] ?? null])->save();
            $this->audit->record($actor, 'reports', $locked->id, 'report.status_changed', $from, $locked->status->value, null);
            if (in_array($locked->status, [ReportStatus::Resolved, ReportStatus::Dismissed], true)) {
                $this->notify($locked);
            }

            return $locked;
        }, 3);
    }

    /**
     * Listings go live without review, so the community is the first filter: once enough different users
     * have an open report on a live listing, it is hidden and waits for an admin (PENDING). The listing row is
     * already locked by create(). A listing held by an auction is left to the admin, so bidders are not cut off.
     */
    private function hideWhenReportedEnough(User $actor, Product $product): void
    {
        $threshold = max(1, (int) config('moderation.auto_hide_reports'));
        $reporters = Report::where('target_type', ReportTargetType::Product)->where('target_id', $product->id)
            ->whereIn('status', [ReportStatus::Pending, ReportStatus::InReview])->distinct()->count('reporter_user_id');
        if ($reporters < $threshold || $product->status !== ProductStatus::ACTIVE || app(FixedPriceEligibility::class)->claims()->where('product_id', $product->id)->exists()) {
            return;
        }
        $reason = "Tạm ẩn vì {$reporters} người dùng báo cáo; đang chờ quản trị viên xem xét.";
        $product->forceFill(['status' => ProductStatus::PENDING, 'moderation_reason' => $reason])->save();
        $this->audit->record($actor, 'products', $product->id, 'product.auto_hidden', ProductStatus::ACTIVE->value, ProductStatus::PENDING->value, $reason);
        $owner = User::find(Shop::withTrashed()->whereKey($product->shop_id)->value('owner_id'));
        if (! $owner) {
            return;
        }
        $row = $owner->notifications()->firstOrCreate(['dedup_key' => hash('sha256', 'product-auto-hidden|'.$product->id.'|'.$product->updated_at?->toISOString())], ['id' => (string) Str::uuid(), 'type' => 'PRODUCT_HIDDEN', 'data' => ['type' => 'PRODUCT_HIDDEN', 'message' => "Tin \"{$product->name}\" đã tạm ẩn vì nhiều người báo cáo. Quản trị viên sẽ xem xét.", 'target_route' => '/my-ads?tab=pending', 'product_id' => (string) $product->id]]);
        if ($row->wasRecentlyCreated) {
            $payload = (new NotificationResource($row))->resolve();
            DB::afterCommit(fn () => event(new NotificationCreated($owner->id, $payload)));
        }
    }

    private function notify(Report $report): void
    {
        $user = User::find($report->reporter_user_id);
        if (! $user) {
            return;
        }
        $message = $report->status === ReportStatus::Resolved ? 'Báo cáo của bạn đã được xử lý.' : 'Báo cáo của bạn đã được xem xét và không phát hiện vi phạm.';
        $row = $user->notifications()->firstOrCreate(['dedup_key' => hash('sha256', 'report-result|'.$report->id)], ['id' => (string) Str::uuid(), 'type' => 'REPORT_RESULT', 'data' => ['type' => 'REPORT_RESULT', 'message' => $message, 'target_route' => '/reports', 'report_id' => (string) $report->id]]);
        if ($row->wasRecentlyCreated) {
            event(new NotificationCreated($user->id, (new NotificationResource($row))->resolve()));
        }
    }

    /**
     * Who answers for each reported target: the shop owner for products and shops, the author for comments.
     *
     * @return array<int, int> report id => owner user id
     */
    public function targetOwners(Collection $reports): array
    {
        $owners = [];
        foreach (ReportTargetType::cases() as $type) {
            $group = $reports->filter(fn (Report $r) => $r->target_type === $type);
            if ($group->isEmpty()) {
                continue;
            }
            $ids = $group->pluck('target_id')->unique()->all();
            $byTarget = match ($type) {
                ReportTargetType::Product => DB::table('products')->join('shops', 'shops.id', '=', 'products.shop_id')->whereIn('products.id', $ids)->pluck('shops.owner_id', 'products.id'),
                ReportTargetType::Shop => DB::table('shops')->whereIn('id', $ids)->pluck('owner_id', 'id'),
                ReportTargetType::VideoComment => DB::table('video_comments')->whereIn('id', $ids)->pluck('user_id', 'id'),
            };
            foreach ($group as $report) {
                if (isset($byTarget[$report->target_id])) {
                    $owners[$report->id] = (int) $byTarget[$report->target_id];
                }
            }
        }

        return $owners;
    }

    /** Batch target lookups per type; user resources never contain hidden target context. */
    public function decorate(Collection $reports, bool $admin): void
    {
        if ($admin) {
            $owners = $this->targetOwners($reports);
            foreach ($reports as $report) {
                $report->setAttribute('target_owner_id', $owners[$report->id] ?? null);
            }
        }
        foreach (ReportTargetType::cases() as $type) {
            $group = $reports->filter(fn (Report $r) => $r->target_type === $type);
            if ($group->isEmpty()) {
                continue;
            }
            $query = $admin ? match ($type) {
                ReportTargetType::Product => Product::withTrashed()->with(['shop' => fn ($q) => $q->withTrashed()->with(['owner' => fn ($q) => $q->withTrashed()])]),ReportTargetType::Shop => Shop::withTrashed()->with(['owner' => fn ($q) => $q->withTrashed()]),ReportTargetType::VideoComment => VideoComment::with(['user', 'video.product' => fn ($q) => $q->withTrashed()])
            } : $this->publicTargets($type);
            if (! $admin && $type === ReportTargetType::VideoComment) {
                $query->with('video');
            }
            $targets = $query->whereIn('id', $group->pluck('target_id'))->get()->keyBy('id');
            $publicIds = $admin ? $this->publicTargets($type)->whereIn('id', $group->pluck('target_id'))->pluck('id')->all() : [];
            foreach ($group as $report) {
                $target = $targets->get($report->target_id);
                $summary = ['id' => (string) $report->target_id, 'type' => $type->value, 'label' => $type->label(), 'available' => (bool) $target, 'name' => $admin ? $report->target_name : 'Nội dung không còn công khai', 'route' => null];
                if ($target) {
                    $summary['name'] = $type === ReportTargetType::VideoComment ? 'Bình luận video #'.$target->id : $target->name;
                    $summary['status'] = $target->status->value;
                    $summary['route'] = match ($type) {
                        ReportTargetType::Product => $admin ? '/admin/products?inspect='.$target->id : '/products/'.$target->slug,ReportTargetType::Shop => $admin ? '/admin/shops?inspect='.$target->id : '/shops/'.$target->slug,ReportTargetType::VideoComment => $admin ? null : '/?video='.$target->product_video_id
                    };
                    if ($admin) {
                        $summary['public_visible'] = in_array($target->id, $publicIds, true);
                        $summary['deleted'] = method_exists($target, 'trashed') ? $target->trashed() : false;
                        $summary['context'] = match ($type) {
                            ReportTargetType::Product => ['description' => $target->description, 'shop' => $target->shop ? ['id' => (string) $target->shop->id, 'name' => $target->shop->name, 'status' => $target->shop->status->value, 'owner' => $target->shop->owner ? ['id' => (string) $target->shop->owner->id, 'name' => $target->shop->owner->name] : null] : null],
                            ReportTargetType::Shop => ['description' => $target->description, 'owner' => $target->owner ? ['id' => (string) $target->owner->id, 'name' => $target->owner->name] : null],
                            ReportTargetType::VideoComment => ['body' => $target->body, 'author' => $target->user ? ['id' => (string) $target->user->id, 'name' => $target->user->name] : null, 'video_id' => (string) $target->product_video_id, 'product' => $target->video?->product ? ['id' => (string) $target->video->product->id, 'name' => $target->video->product->name, 'route' => '/admin/products?inspect='.$target->video->product->id] : null],
                        };
                    }
                }
                $report->setAttribute('safe_target', $summary);
            }
        }
    }
}
