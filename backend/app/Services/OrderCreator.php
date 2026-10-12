<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\ShopOrderStatus;
use App\Models\Address;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class OrderCreator
{
    public function __construct(private AuditService $audit) {}

    /** Caller holds account, address, shop, category and product locks inside a transaction. */
    public function create(User $buyer, Address $address, string $key, string $hash, PaymentMethod $method, array $channels, array $groups, Collection $products, string $total): Order
    {
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

        return $order;
    }
}
