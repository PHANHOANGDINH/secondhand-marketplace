<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\ShopOrderStatus;
use App\Exceptions\CheckoutPlanChanged;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(private OrderLocks $locks, private AuditService $audit) {}

    /** @return array{order: Order, created: bool} */
    public function checkout(User $user, int $addressId, string $key, PaymentMethod $method = PaymentMethod::COD, array $channels = []): array
    {
        sort($channels);
        $payload = ['address_id' => (string) $addressId, 'payment_method' => $method->value];
        if ($method === PaymentMethod::ZALOPAY) {
            app(ZaloPayGateway::class)->configured();
            $payload['preferred_payment_method'] = $channels;
        }
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        for ($plan = 0; $plan < 3; $plan++) {
            $owners = DB::table('cart_items')->join('carts', 'carts.id', '=', 'cart_items.cart_id')->join('products', 'products.id', '=', 'cart_items.product_id')->join('shops', 'shops.id', '=', 'products.shop_id')->where('carts.user_id', $user->id)->pluck('shops.owner_id')->all();
            try {
                return $this->locks->transaction(function () use ($user, $addressId, $key, $hash, $owners, $method, $channels): array {
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
                    $items = $cart?->items()->orderBy('id')->lockForUpdate()->get();
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
                        $image = $product->images()->where('is_primary', true)->lockForUpdate()->first();
                        abort_unless($image, 409, 'A cart item no longer has a primary image.');
                        $amount = OrderSummary::checked(bcmul($product->price, (string) $item->quantity, 2));
                        $groups[$shop->id][] = ['product_id' => $product->id, 'product_name' => $product->name, 'product_image' => $image->image_path, 'product_price' => $product->price, 'quantity' => $item->quantity, 'subtotal' => $amount];
                        $total = OrderSummary::checked(bcadd($total, $amount, 2));
                    }
                    if ($method === PaymentMethod::ZALOPAY) {
                        ZaloPayGateway::amount($total);
                    }
                    $shipping = [];
                    foreach (['recipient_name', 'phone', 'address_line', 'ward', 'district', 'province', 'country_code'] as $field) {
                        $shipping['shipping_'.$field] = $address->$field;
                    }
                    $order = Order::create([...$shipping, 'buyer_id' => $buyer->id, 'order_code' => 'SHM-'.Str::upper(Str::random(24)), 'checkout_key' => $key, 'request_hash' => $hash, 'subtotal' => $total, 'shipping_fee' => '0.00', 'discount_amount' => '0.00', 'total_amount' => $total, 'currency' => 'VND', 'payment_method' => $method]);
                    foreach ($groups as $shopId => $rows) {
                        $subtotal = array_reduce($rows, fn (string $sum, array $row): string => bcadd($sum, $row['subtotal'], 2), '0.00');
                        $part = $order->shopOrders()->create(['shop_id' => $shopId, 'status' => ShopOrderStatus::PENDING, 'subtotal' => $subtotal, 'shipping_fee' => '0.00', 'discount_amount' => '0.00', 'total_amount' => $subtotal]);
                        foreach ($rows as $row) {
                            $part->orderItems()->create($row);
                            $product = $products[$row['product_id']];
                            $product->stock_quantity -= $row['quantity'];
                            if ($product->stock_quantity === 0) {
                                $product->status = ProductStatus::SOLD;
                            }
                            $product->save();
                            if ($product->status === ProductStatus::SOLD) {
                                $this->audit->record($buyer, 'products', $product->id, 'product.checkout_sold', 'ACTIVE', 'SOLD', $product->updated_at->format('Y-m-d H:i:s.u'));
                            }
                        }
                        $this->audit->record($buyer, 'shop_orders', $part->id, 'shop_order.created', null, 'PENDING', null);
                    }
                    $payment = $method === PaymentMethod::ZALOPAY ? app(ZaloPayService::class)->newAttempt($order, 1, $channels) : $order->payments()->create(['attempt_number' => 1, 'method' => PaymentMethod::COD, 'amount' => $total, 'currency' => 'VND', 'status' => PaymentStatus::PENDING, 'transaction_reference' => 'COD-'.Str::uuid()]);
                    $this->audit->record($buyer, 'payments', $payment->id, 'payment.created', null, 'PENDING', null);
                    $cart->items()->delete();
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
