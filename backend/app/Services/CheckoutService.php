<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\ProductStatus;
use App\Exceptions\CheckoutPlanChanged;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(private OrderLocks $locks) {}

    /**
     * Checks out the whole cart, or only `$itemIds` (cart item ids) when given: the other items stay in the cart.
     *
     * @return array{order: Order, created: bool}
     */
    public function checkout(User $user, int $addressId, string $key, PaymentMethod $method = PaymentMethod::COD, array $channels = [], array $itemIds = []): array
    {
        sort($channels);
        $itemIds = array_values(array_unique(array_map('intval', $itemIds)));
        sort($itemIds);
        $payload = ['address_id' => (string) $addressId, 'payment_method' => $method->value];
        if ($method === PaymentMethod::ZALOPAY) {
            app(ZaloPayGateway::class)->configured();
            $payload['preferred_payment_method'] = $channels;
        }
        // The selection is part of the request identity: the same key with another selection is a conflict.
        // A whole-cart checkout keeps the hash it always had.
        if ($itemIds !== []) {
            $payload['cart_item_ids'] = array_map('strval', $itemIds);
        }
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        for ($plan = 0; $plan < 3; $plan++) {
            $owners = DB::table('cart_items')->join('carts', 'carts.id', '=', 'cart_items.cart_id')->join('products', 'products.id', '=', 'cart_items.product_id')->join('shops', 'shops.id', '=', 'products.shop_id')->where('carts.user_id', $user->id)->when($itemIds !== [], fn ($query) => $query->whereIn('cart_items.id', $itemIds))->pluck('shops.owner_id')->all();
            try {
                return $this->locks->transaction(function () use ($user, $addressId, $key, $hash, $owners, $method, $channels, $itemIds): array {
                    $accounts = $this->locks->accounts([...$owners, $user->id]);
                    $buyer = $accounts[$user->id];
                    $this->locks->active($buyer);
                    $cart = Cart::where('user_id', $buyer->id)->lockForUpdate()->first();
                    $prior = Order::where('buyer_id', $buyer->id)->where('checkout_key', $key)->lockForUpdate()->first();
                    if ($prior) {
                        abort_unless(hash_equals($prior->request_hash, $hash), 409, 'Idempotency key was already used with a different payload.');

                        return ['order' => $prior->load(OrderSummary::RELATIONS), 'created' => false];
                    }
                    $address = Address::whereKey($addressId)->where('user_id', $buyer->id)->lockForUpdate()->first();
                    if (! $address) {
                        throw ValidationException::withMessages(['address_id' => ['Choose an address belonging to your account.']]);
                    }
                    $items = $cart?->items()->when($itemIds !== [], fn ($query) => $query->whereIn('id', $itemIds))->orderBy('id')->lockForUpdate()->get();
                    if ($itemIds !== [] && (! $items || $items->count() !== count($itemIds))) {
                        throw ValidationException::withMessages(['cart_item_ids' => ['A selected item is no longer in your cart.']]);
                    }
                    if (! $items || $items->isEmpty()) {
                        throw ValidationException::withMessages(['cart' => ['Your cart is empty.']]);
                    }
                    $shopIds = Product::withTrashed()->whereIn('id', $items->pluck('product_id'))->pluck('shop_id');
                    $shops = Shop::withTrashed()->whereIn('id', $shopIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                    foreach ($shops as $shop) {
                        if (! $accounts->has($shop->owner_id)) {
                            throw new CheckoutPlanChanged;
                        }
                    }
                    $categories = $this->locks->categories();
                    $products = Product::withTrashed()->whereIn('id', $items->pluck('product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                    $total = '0.00';
                    $groups = [];
                    foreach ($items as $item) {
                        $product = $products->get($item->product_id);
                        $shop = $product ? $shops->get($product->shop_id) : null;
                        if ($product && ! $shop) {
                            throw new CheckoutPlanChanged;
                        }
                        abort_if($shop?->owner_id === $buyer->id, 403, 'You cannot buy from your own shop.');
                        abort_unless($product && ! $product->trashed() && $product->status === ProductStatus::ACTIVE && $product->stock_quantity >= $item->quantity && $item->quantity >= 1 && $item->quantity <= 99 && $this->locks->eligible($shop, $accounts[$shop->owner_id], $categories, $product->category_id), 409, 'A cart item is unavailable or has insufficient stock. Refresh your cart.');
                        app(FixedPriceEligibility::class)->assertAvailable($product->id);
                        $image = $product->images()->where('is_primary', true)->lockForUpdate()->first();
                        abort_unless($image, 409, 'A cart item no longer has a primary image.');
                        $amount = OrderSummary::checked(bcmul($product->price, (string) $item->quantity, 2));
                        $groups[$shop->id][] = ['product_id' => $product->id, 'product_name' => $product->name, 'product_image' => $image->image_path, 'product_price' => $product->price, 'quantity' => $item->quantity, 'subtotal' => $amount];
                        $total = OrderSummary::checked(bcadd($total, $amount, 2));
                    }
                    if ($method === PaymentMethod::ZALOPAY) {
                        ZaloPayGateway::amount($total);
                    }
                    $order = app(OrderCreator::class)->create($buyer, $address, $key, $hash, $method, $channels, $groups, $products, $total);
                    $cart->items()->whereIn('id', $items->pluck('id'))->delete();
                    $cart->touch();

                    return ['order' => $order->load(OrderSummary::RELATIONS), 'created' => true];
                });
            } catch (CheckoutPlanChanged) {
                continue;
            }
        }
        abort(409, 'Cart changed during checkout. Retry with the same idempotency key.');
    }
}
