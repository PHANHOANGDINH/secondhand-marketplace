<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class AdminActionCenterService
{
    private const OPEN = ['PENDING', 'IN_REVIEW'];

    /** Owners with enough open reports against their shop, products or video comments in the configured window. */
    public function flaggedOwners(): QueryBuilder
    {
        $since = now()->subDays(config('admin-action-center.flagged_window_days'));
        $open = fn (string $type): QueryBuilder => DB::table('reports')->where('reports.target_type', $type)->whereIn('reports.status', self::OPEN)->where('reports.created_at', '>=', $since);
        $owners = $open('SHOP')->join('shops', 'shops.id', '=', 'reports.target_id')->select('shops.owner_id as owner_id')
            ->unionAll($open('PRODUCT')->join('products', 'products.id', '=', 'reports.target_id')->join('shops', 'shops.id', '=', 'products.shop_id')->select('shops.owner_id as owner_id'))
            ->unionAll($open('VIDEO_COMMENT')->join('video_comments', 'video_comments.id', '=', 'reports.target_id')->select('video_comments.user_id as owner_id'));

        return DB::query()->fromSub($owners, 'reported_owners')->select('owner_id')->groupBy('owner_id')->havingRaw('COUNT(*) >= ?', [config('admin-action-center.flagged_min_reports')]);
    }

    public function unresolvedPayments(): Builder
    {
        return Payment::where('reconciliation_required', true)->whereNull('reconciliation_resolved_at');
    }

    /** Unfinished auctions whose end passed long enough ago that the scheduler should already have closed them. */
    public function overdueAuctions(Builder $query): Builder
    {
        return $query->whereIn('status', ['SCHEDULED', 'LIVE'])->where('ends_at', '<', now()->subMinutes(config('admin-action-center.overdue_grace_minutes')));
    }

    public function settlement(Builder $query, string $state): Builder
    {
        $query->where('status', 'ENDED');

        return match ($state) {
            'NO_WINNER' => $query->whereNull('winner_user_id'),
            'ORDER_CREATED' => $query->whereNotNull('winner_order_id'),
            'AWAITING_CHECKOUT' => $query->whereNotNull('winner_user_id')->whereNull('winner_order_id')->where('winner_checkout_expires_at', '>', now()),
            default => $query->whereNotNull('winner_user_id')->whereNull('winner_order_id')->where(fn (Builder $q) => $q->whereNull('winner_checkout_expires_at')->orWhere('winner_checkout_expires_at', '<=', now())),
        };
    }

    /** A fixed number of aggregate queries, independent of how many rows match. */
    public function summary(): array
    {
        $time = now();
        $reports = DB::table('reports')->whereIn('status', self::OPEN)->select('target_type', 'status')->selectRaw('COUNT(*) AS total')->groupBy('target_type', 'status')->get();
        $byTarget = ['PRODUCT' => 0, 'SHOP' => 0, 'VIDEO_COMMENT' => 0];
        $byStatus = ['PENDING' => 0, 'IN_REVIEW' => 0];
        foreach ($reports as $row) {
            $byTarget[$row->target_type] = ($byTarget[$row->target_type] ?? 0) + (int) $row->total;
            $byStatus[$row->status] += (int) $row->total;
        }
        $overdueBefore = $time->copy()->subMinutes(config('admin-action-center.overdue_grace_minutes'));
        $auctions = Auction::toBase()->selectRaw("COALESCE(SUM(status = 'ENDED' AND winner_user_id IS NOT NULL AND winner_order_id IS NULL AND winner_checkout_expires_at > ?), 0) AS awaiting, COALESCE(SUM(status IN ('SCHEDULED', 'LIVE') AND ends_at < ?), 0) AS overdue", [$time, $overdueBefore])->first();

        return [
            'server_time' => $time->toISOString(),
            'flagged_min_reports' => config('admin-action-center.flagged_min_reports'),
            'flagged_window_days' => config('admin-action-center.flagged_window_days'),
            'overdue_grace_minutes' => config('admin-action-center.overdue_grace_minutes'),
            'shops_pending' => DB::table('shops')->where('status', 'PENDING')->whereNull('deleted_at')->count(),
            'products_pending' => DB::table('products')->where('status', 'PENDING')->whereNull('deleted_at')->count(),
            'reports_open' => array_sum($byStatus),
            'reports_pending' => $byStatus['PENDING'],
            'reports_in_review' => $byStatus['IN_REVIEW'],
            'reports_by_target' => $byTarget,
            'users_flagged' => DB::query()->fromSub($this->flaggedOwners(), 'flagged')->count(),
            'payments_reconciliation' => $this->unresolvedPayments()->count(),
            'auctions_awaiting_checkout' => (int) $auctions->awaiting,
            'auctions_overdue' => (int) $auctions->overdue,
            'video_comments_reported' => (int) DB::table('reports')->where('target_type', 'VIDEO_COMMENT')->whereIn('status', self::OPEN)->distinct()->count('target_id'),
        ];
    }
}
