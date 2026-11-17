<?php

namespace App\Services;

use App\Enums\ReviewStatus;
use App\Enums\ShopOrderStatus;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** Shop star ratings: one per completed order at the shop, by its buyer; each rating stays editable. */
class ShopReviewService
{
    public function __construct(private OrderLocks $locks) {}

    public static function published(Builder $query): Builder
    {
        return $query->where('status', ReviewStatus::PUBLISHED);
    }

    /** The buyer's completed orders at this shop, newest first. */
    private function completed(User $user, Shop $shop): Builder
    {
        return ShopOrder::where('shop_id', $shop->id)->where('status', ShopOrderStatus::COMPLETED)
            ->whereHas('order', fn (Builder $orders) => $orders->where('buyer_id', $user->id));
    }

    /** Completed orders not rated yet, newest first. */
    public function unrated(User $user, Shop $shop): Builder
    {
        return $this->completed($user, $shop)->whereNotIn('id', ShopReview::whereNotNull('shop_order_id')->select('shop_order_id'))
            ->orderByDesc('completed_at')->orderByDesc('id');
    }

    public function eligible(User $user, Shop $shop): bool
    {
        return $user->id !== $shop->owner_id && $this->unrated($user, $shop)->exists();
    }

    /** Rates the buyer's newest completed order here that has no rating yet. */
    public function create(User $user, Shop $shop, array $data): ShopReview
    {
        return $this->locks->transaction(function () use ($user, $shop, $data): ShopReview {
            // Same order as product reviews: accounts, then the shop, then the order and the review rows.
            $actor = $this->locks->accounts([$user->id, $shop->owner_id])[$user->id];
            $this->locks->active($actor);
            $locked = Shop::whereKey($shop->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->owner_id === $actor->id, 403, 'Bạn không thể tự đánh giá gian hàng của mình.');
            $part = $this->unrated($actor, $locked)->lockForUpdate()->first();
            if (! $part) {
                abort_if($this->completed($actor, $locked)->exists(), 409, 'Bạn đã đánh giá mọi đơn hoàn tất ở gian hàng này.');
                abort(403, 'Chỉ người đã mua và nhận hàng hoàn tất từ gian hàng mới đánh giá được.');
            }

            return ShopReview::create([...$this->values($data), 'shop_id' => $locked->id, 'user_id' => $actor->id, 'shop_order_id' => $part->id, 'status' => ReviewStatus::PUBLISHED])->load('user');
        });
    }

    /** Editing keeps a moderation decision: a hidden rating stays hidden. */
    public function update(User $user, ShopReview $review, array $data): ShopReview
    {
        $shop = Shop::withTrashed()->findOrFail($review->shop_id);

        return $this->locks->transaction(function () use ($user, $shop, $review, $data): ShopReview {
            $actor = $this->locks->accounts([$user->id, $shop->owner_id])[$user->id];
            $this->locks->active($actor);
            Shop::withTrashed()->whereKey($shop->id)->lockForUpdate()->firstOrFail();
            $locked = ShopReview::whereKey($review->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->user_id === $actor->id, 403, 'Bạn chỉ sửa được đánh giá của chính mình.');
            $locked->update($this->values($data));

            return $locked->load('user');
        });
    }

    private function values(array $data): array
    {
        $comment = isset($data['comment']) ? trim((string) $data['comment']) : '';

        return ['rating' => (int) $data['rating'], 'comment' => $comment === '' ? null : $comment];
    }

    /** @return array{average: float|null, count: int, distribution: array<string, int>} */
    public static function summary(int $shopId): array
    {
        $rows = self::published(ShopReview::where('shop_id', $shopId))->selectRaw('rating, COUNT(*) AS total')->groupBy('rating')->pluck('total', 'rating');
        $count = (int) $rows->sum();
        $weighted = 0;
        $distribution = [];
        foreach ([5, 4, 3, 2, 1] as $stars) {
            $distribution[(string) $stars] = (int) ($rows[$stars] ?? 0);
            $weighted += $stars * $distribution[(string) $stars];
        }

        return ['average' => $count ? round($weighted / $count, 1) : null, 'count' => $count, 'distribution' => $distribution];
    }
}
