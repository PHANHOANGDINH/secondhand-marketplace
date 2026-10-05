<?php

namespace App\Services;

use App\Enums\ReviewStatus;
use App\Enums\ShopOrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ReviewService
{
    public function __construct(private OrderLocks $locks, private AuditService $audit) {}

    /** The same visibility predicate backs public lists and SQL aggregates. */
    public static function published(Builder $query): Builder
    {
        return $query->where('status', ReviewStatus::PUBLISHED)->whereHas('orderItem', function (Builder $items): void {
            $items->whereColumn('product_id', 'reviews.product_id')->whereHas('shopOrder', function (Builder $parts): void {
                $parts->where('status', ShopOrderStatus::COMPLETED)
                    ->whereHas('order', fn (Builder $orders) => $orders->whereColumn('buyer_id', 'reviews.user_id'))
                    ->whereHas('shop', fn (Builder $shops) => $shops->whereColumn('owner_id', '!=', 'reviews.user_id'));
            });
        });
    }

    public static function withSummary(Builder $products): Builder
    {
        return $products->withCount(['reviews as review_count' => fn (Builder $reviews) => self::published($reviews)])
            ->withAvg(['reviews as average_rating' => fn (Builder $reviews) => self::published($reviews)], 'rating');
    }

    public function eligibleItems(User $buyer, Product $product): Builder
    {
        return OrderItem::where('product_id', $product->id)->whereHas('shopOrder', function (Builder $parts) use ($buyer, $product): void {
            $parts->where('status', ShopOrderStatus::COMPLETED)->where('shop_id', $product->shop_id)
                ->whereHas('order', fn (Builder $orders) => $orders->where('buyer_id', $buyer->id))
                ->whereHas('shop', fn (Builder $shops) => $shops->where('owner_id', '!=', $buyer->id));
        })->with('review')->orderByDesc('id');
    }

    public static function describeItem(OrderItem $item, User $buyer, bool $completed, int $ownerId): void
    {
        $eligible = $completed && $ownerId !== $buyer->id;
        $item->setAttribute('can_review', $eligible && $item->review === null);
        $item->setAttribute('can_edit_review', $eligible && $item->review?->user_id === $buyer->id);
    }

    public function create(User $user, OrderItem $item, array $data): Review
    {
        Gate::forUser($user)->authorize('create', [Review::class, $item]);

        return $this->save($user, $item, $data);
    }

    public function update(User $user, Review $review, array $data): Review
    {
        Gate::forUser($user)->authorize('update', $review);

        return $this->save($user, $review->orderItem, $data, $review);
    }

    private function save(User $user, OrderItem $item, array $data, ?Review $existing = null): Review
    {
        $part = $item->shopOrder;
        $ownerId = $part->shop->owner_id;

        return $this->locks->transaction(function () use ($user, $item, $part, $ownerId, $data, $existing): Review {
            $accounts = $this->locks->accounts([$user->id, $ownerId]);
            $actor = $accounts[$user->id];
            $this->locks->active($actor);
            $order = Order::whereKey($part->order_id)->lockForUpdate()->firstOrFail();
            $lockedPart = ShopOrder::whereKey($part->id)->lockForUpdate()->firstOrFail();
            $shop = Shop::withTrashed()->whereKey($lockedPart->shop_id)->lockForUpdate()->firstOrFail();
            $product = Product::withTrashed()->whereKey($item->product_id)->lockForUpdate()->firstOrFail();
            $lockedItem = OrderItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $lockedPart->setRelation('order', $order);
            $lockedItem->setRelation('shopOrder', $lockedPart);
            Gate::forUser($actor)->authorize('create', [Review::class, $lockedItem]);
            abort_if($shop->owner_id === $actor->id, 403, 'You cannot review your own shop products.');
            abort_unless($lockedPart->status === ShopOrderStatus::COMPLETED, 409, 'Only completed purchases can be reviewed.');
            abort_unless($lockedItem->product_id === $product->id && $product->shop_id === $shop->id, 409, 'Order item product reference is inconsistent.');
            $review = Review::where('order_item_id', $lockedItem->id)->lockForUpdate()->first();
            if ($existing) {
                abort_unless($review && $review->id === $existing->id, 404);
                Gate::forUser($actor)->authorize('update', $review);
                abort_unless($review->product_id === $product->id, 409, 'Review product reference is inconsistent.');
                $review->update($data);
            } else {
                abort_if($review !== null, 409, 'This order item already has a review.');
                $review = Review::create([...$data, 'user_id' => $actor->id, 'product_id' => $product->id, 'order_item_id' => $lockedItem->id, 'status' => ReviewStatus::PUBLISHED]);
            }

            return $review->load('user');
        });
    }

    public function moderate(User $actor, Review $review, array $data): Review
    {
        return DB::transaction(function () use ($actor, $review, $data): Review {
            $admin = $this->locks->accounts([$actor->id])[$actor->id];
            $locked = Review::whereKey($review->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($admin)->authorize('moderate', $locked);
            $from = $locked->status->value;
            abort_unless($data['expected_status'] === $from && $data['status'] !== $from, 409, 'Review status has changed or transition is invalid.');
            $locked->update(['status' => $data['status'], 'moderation_reason' => $data['reason']]);
            $this->audit->record($admin, 'reviews', $locked->id, 'review.moderated', $from, $data['status'], $data['reason']);

            return $locked->load(['user', 'product']);
        }, 3);
    }
}
