<?php

namespace App\Services;

use App\Enums\ReviewStatus;
use App\Enums\ShopOrderStatus;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** Shop star ratings: only buyers with a completed order at the shop, one rating each, editable. */
class ShopReviewService
{
    public function __construct(private OrderLocks $locks) {}

    public static function published(Builder $query): Builder
    {
        return $query->where('status', ReviewStatus::PUBLISHED);
    }

    public function eligible(User $user, Shop $shop): bool
    {
        return $user->id !== $shop->owner_id && ShopOrder::where('shop_id', $shop->id)->where('status', ShopOrderStatus::COMPLETED)
            ->whereHas('order', fn (Builder $orders) => $orders->where('buyer_id', $user->id))->exists();
    }

    public function save(User $user, Shop $shop, array $data): ShopReview
    {
        return $this->locks->transaction(function () use ($user, $shop, $data): ShopReview {
            // Same order as product reviews: accounts, then the shop, then the review row.
            $actor = $this->locks->accounts([$user->id, $shop->owner_id])[$user->id];
            $this->locks->active($actor);
            $locked = Shop::whereKey($shop->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->owner_id === $actor->id, 403, 'Bạn không thể tự đánh giá gian hàng của mình.');
            abort_unless($this->eligible($actor, $locked), 403, 'Chỉ người đã mua và nhận hàng hoàn tất từ gian hàng mới đánh giá được.');
            $comment = isset($data['comment']) ? trim((string) $data['comment']) : '';
            $values = ['rating' => (int) $data['rating'], 'comment' => $comment === '' ? null : $comment];
            $review = ShopReview::where('shop_id', $locked->id)->where('user_id', $actor->id)->lockForUpdate()->first();
            if ($review) {
                // Editing keeps a moderation decision: a hidden rating stays hidden.
                $review->update($values);
            } else {
                $review = ShopReview::create([...$values, 'shop_id' => $locked->id, 'user_id' => $actor->id, 'status' => ReviewStatus::PUBLISHED]);
            }

            return $review->load('user');
        });
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
