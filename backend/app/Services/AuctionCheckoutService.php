<?php

namespace App\Services;

use App\Enums\AuctionStatus;
use App\Enums\PaymentMethod;
use App\Enums\ProductStatus;
use App\Models\Address;
use App\Models\Auction;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AuctionCheckoutService
{
    public function __construct(private OrderLocks $locks, private OrderCreator $creator) {}

    /** @return array{order: Order, created: bool} */
    public function checkout(User $user, Auction $auction, int $addressId, string $key, PaymentMethod $method = PaymentMethod::COD, array $channels = []): array
    {
        Gate::forUser($user)->authorize('checkout', $auction);
        sort($channels);
        $hash = hash('sha256', json_encode(['auction_id' => (string) $auction->id, 'address_id' => (string) $addressId, 'payment_method' => $method->value, 'preferred_payment_method' => $method === PaymentMethod::ZALOPAY ? $channels : []], JSON_THROW_ON_ERROR));
        $product = Product::withTrashed()->findOrFail($auction->product_id);
        $ownerId = Shop::withTrashed()->whereKey($product->shop_id)->value('owner_id');

        return $this->locks->transaction(function () use ($user, $auction, $product, $ownerId, $addressId, $key, $hash, $method, $channels): array {
            $accounts = $this->locks->accounts([$user->id, $ownerId]);
            $buyer = $accounts[$user->id];
            $this->locks->active($buyer);
            $address = Address::whereKey($addressId)->where('user_id', $buyer->id)->lockForUpdate()->first();
            $shop = Shop::withTrashed()->whereKey($product->shop_id)->lockForUpdate()->firstOrFail();
            $categories = $this->locks->categories();
            $products = Product::withTrashed()->whereKey($product->id)->lockForUpdate()->get()->keyBy('id');
            $lockedProduct = $products[$product->id];
            $locked = Auction::whereKey($auction->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($buyer)->authorize('checkout', $locked);
            abort_unless($locked->status === AuctionStatus::ENDED && $locked->product_id === $lockedProduct->id && $locked->seller_id === $shop->owner_id && $lockedProduct->shop_id === $shop->id, 409, 'Auction relationship or result changed.');
            if ($locked->winner_order_id) {
                $prior = Order::whereKey($locked->winner_order_id)->where('buyer_id', $buyer->id)->lockForUpdate()->firstOrFail();
                abort_unless(hash_equals($prior->request_hash, $hash), 409, 'Auction order already exists with a different checkout payload.');

                return ['order' => $prior->load(OrderSummary::RELATIONS), 'created' => false];
            }
            abort_unless($locked->winner_checkout_expires_at?->isFuture(), 409, 'Quyền mua đã hết hạn.');
            abort_if(Order::where('buyer_id', $buyer->id)->where('checkout_key', $key)->lockForUpdate()->first(), 409, 'Idempotency key was already used for another checkout.');
            if (! $address) {
                throw ValidationException::withMessages(['address_id' => ['Choose an address belonging to your account.']]);
            }
            abort_unless(! $lockedProduct->trashed() && $lockedProduct->status === ProductStatus::ACTIVE && $lockedProduct->stock_quantity >= 1 && $accounts->has($shop->owner_id) && $this->locks->eligible($shop, $accounts[$shop->owner_id], $categories, $lockedProduct->category_id), 409, 'Sản phẩm hoặc gian hàng hiện không thể giao hàng.');
            $bid = $locked->bids()->whereKey($locked->winning_bid_id)->where('user_id', $buyer->id)->lockForUpdate()->first();
            abort_unless($bid, 409, 'Invalid winning bid.');
            $image = $lockedProduct->images()->where('is_primary', true)->lockForUpdate()->first();
            abort_unless($image, 409, 'Product no longer has a primary image.');
            $total = OrderSummary::checked($bid->amount);
            if ($method === PaymentMethod::ZALOPAY) {
                app(ZaloPayGateway::class)->configured();
                ZaloPayGateway::amount($total);
            }
            $groups = [$shop->id => [['auction_id' => $locked->id, 'product_id' => $lockedProduct->id, 'product_name' => $lockedProduct->name, 'product_image' => $image->image_path, 'product_price' => $total, 'quantity' => 1, 'subtotal' => $total]]];
            $order = $this->creator->create($buyer, $address, $key, $hash, $method, $channels, $groups, $products, $total);
            $locked->forceFill(['winner_order_id' => $order->id, 'settled_at' => now()])->save();
            app(AuctionNotificationService::class)->orderCreated($locked->setRelation('product', $lockedProduct), $order);

            return ['order' => $order->load(OrderSummary::RELATIONS), 'created' => true];
        });
    }
}
