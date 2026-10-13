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
    public function create(User $buyer, Address $address, string $key, string $hash, PaymentMethod $method, array $channels, array $groups, Collection $products, string $total, ?array $plan = null): Order
    {
        $shipping = [];
        foreach (['recipient_name', 'phone', 'address_line', 'ward', 'district', 'province', 'country_code'] as $field) {
            $shipping['shipping_'.$field] = $address->$field;
        }
        // With vouchers the order and each shop order store their own discount and code: later voucher edits change nothing here.
        $payable = $plan['total'] ?? $total;
        $order = Order::create([...$shipping, 'buyer_id' => $buyer->id, 'order_code' => 'SHM-'.Str::upper(Str::random(24)), 'checkout_key' => $key, 'request_hash' => $hash, 'subtotal' => $total, 'shipping_fee' => '0.00', 'discount_amount' => $plan['discount'] ?? '0.00', 'platform_voucher_code' => ($plan['platform'] ?? null)?->code, 'total_amount' => $payable, 'currency' => 'VND', 'payment_method' => $method]);
        foreach ($groups as $shopId => $rows) {
            $subtotal = array_reduce($rows, fn (string $sum, array $row): string => bcadd($sum, $row['subtotal'], 2), '0.00');
            $priced = $plan['parts'][$shopId] ?? [];
            $part = $order->shopOrders()->create(['shop_id' => $shopId, 'status' => ShopOrderStatus::PENDING, 'subtotal' => $subtotal, 'shipping_fee' => '0.00', 'discount_amount' => $priced['discount'] ?? '0.00', 'discount_shop' => $priced['discount_shop'] ?? '0.00', 'discount_platform' => $priced['discount_platform'] ?? '0.00', 'voucher_code' => ($priced['voucher'] ?? null)?->code, 'total_amount' => $priced['total'] ?? $subtotal]);
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
        $payment = $method === PaymentMethod::ZALOPAY ? app(ZaloPayService::class)->newAttempt($order, 1, $channels) : $order->payments()->create(['attempt_number' => 1, 'method' => PaymentMethod::COD, 'amount' => $payable, 'currency' => 'VND', 'status' => PaymentStatus::PENDING, 'transaction_reference' => 'COD-'.Str::uuid()]);
        $this->audit->record($buyer, 'payments', $payment->id, 'payment.created', null, 'PENDING', null);

        return $order;
    }
}
