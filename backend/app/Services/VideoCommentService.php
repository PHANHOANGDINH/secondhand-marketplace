<?php

namespace App\Services;

use App\Enums\ProductVideoStatus;
use App\Enums\UserStatus;
use App\Enums\VideoCommentStatus;
use App\Events\VideoCommentCreated;
use App\Events\VideoCommentHidden;
use App\Http\Requests\Domain\StoreVideoCommentRequest;
use App\Http\Resources\VideoCommentResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVideo;
use App\Models\Shop;
use App\Models\User;
use App\Models\VideoComment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class VideoCommentService
{
    public function __construct(private ProductQuery $products, private AuditService $audit) {}

    public function publicVideos(): Builder
    {
        return ProductVideo::where('status', ProductVideoStatus::Ready)->whereIn('product_id', $this->products->publicVisible()->select('id'));
    }

    public function isPublic(int $id): bool
    {
        return $this->publicVideos()->whereKey($id)->exists();
    }

    private function context(User $actor, ProductVideo $video): array
    {
        $product = Product::withTrashed()->findOrFail($video->product_id);
        $shop = Shop::withTrashed()->findOrFail($product->shop_id);
        $accounts = User::withTrashed()->whereIn('id', [$actor->id, $shop->owner_id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $shop = Shop::withTrashed()->whereKey($shop->id)->lockForUpdate()->firstOrFail();
        Category::orderBy('id')->lockForUpdate()->get();
        $product = Product::withTrashed()->whereKey($product->id)->lockForUpdate()->firstOrFail();
        $video = ProductVideo::whereKey($video->id)->lockForUpdate()->firstOrFail();
        $video->setRelation('product', $product->setRelation('shop', $shop));
        $user = $accounts->get($actor->id);
        abort_unless($user && ! $user->trashed() && $user->status === UserStatus::ACTIVE, 403);

        return [$user, $video];
    }

    public function create(User $user, ProductVideo $video, string $body): VideoComment
    {
        $body = preg_replace('/^[\s\p{Z}]+|[\s\p{Z}]+$/u', '', $body);
        Validator::make(['body' => $body], (new StoreVideoCommentRequest)->rules())->validate();

        return DB::transaction(function () use ($user, $video, $body): VideoComment {
            [$actor,$locked] = $this->context($user, $video);
            Gate::forUser($actor)->authorize('create', [VideoComment::class, $locked]);
            abort_unless($this->isPublic($locked->id), 404);
            $comment = new VideoComment;
            $comment->forceFill(['product_video_id' => $locked->id, 'user_id' => $actor->id, 'body' => $body, 'status' => VideoCommentStatus::Published])->save();
            $comment->setRelation('video', $locked)->setRelation('user', $actor);
            $payload = ['video_id' => (string) $locked->id, 'comment' => (new VideoCommentResource($comment))->publicData(), 'server_time' => now()->toISOString()];
            DB::afterCommit(fn () => event(new VideoCommentCreated($payload)));

            return $comment;
        }, 3);
    }

    public function hide(User $user, VideoComment $comment): void
    {
        DB::transaction(function () use ($user, $comment): void {
            [$actor,$video] = $this->context($user, ProductVideo::findOrFail($comment->product_video_id));
            $locked = VideoComment::whereKey($comment->id)->lockForUpdate()->firstOrFail()->setRelation('video', $video);
            Gate::forUser($actor)->authorize('hide', $locked);
            if ($locked->status === VideoCommentStatus::Hidden) {
                return;
            }
            $locked->forceFill(['status' => VideoCommentStatus::Hidden])->save();
            $this->audit->record($actor, 'video_comments', $locked->id, 'video_comment.hidden', 'PUBLISHED', 'HIDDEN', null);
            $payload = ['video_id' => (string) $video->id, 'comment_id' => (string) $locked->id, 'server_time' => now()->toISOString()];
            DB::afterCommit(fn () => event(new VideoCommentHidden($payload)));
        }, 3);
    }
}
