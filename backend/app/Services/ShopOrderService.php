<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\ShopOrderStatus as Status;
use App\Enums\ShopStatus;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ShopOrderService
{
    public function __construct(private OrderLocks $locks, private AuditService $audit) {}

    public function transition(User $user, ShopOrder $part, array $data, bool $buyer = false): ShopOrder
    {
        Gate::forUser($user)->authorize($buyer ? 'cancel' : 'update', $part);
        $ownerIds = Shop::withTrashed()->whereIn('id', ShopOrder::where('order_id', $part->order_id)->select('shop_id'))->pluck('owner_id')->all();

        return $this->locks->transaction(function () use ($user, $part, $data, $buyer, $ownerIds): ShopOrder {
            $accounts = $this->locks->accounts([...$ownerIds, $user->id]);
            $actor = $accounts[$user->id];
            $this->locks->active($actor);
            $order = Order::whereKey($part->order_id)->lockForUpdate()->firstOrFail();
            $parts = $order->shopOrders()->orderBy('id')->lockForUpdate()->get();
            $locked = $parts->firstWhere('id', $part->id);
            $shops = Shop::withTrashed()->whereIn('id', $parts->pluck('shop_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $locked->setRelation('order', $order)->setRelation('shop', $shops[$locked->shop_id]);
            Gate::forUser($actor)->authorize($buyer ? 'cancel' : 'update', $locked);
            if (! $buyer) {
                abort_unless(! $locked->shop->trashed() && $locked->shop->status === ShopStatus::ACTIVE, 403, 'Shop must be active to change orders.');
            }
            abort_unless(in_array($order->payment_method, [PaymentMethod::COD, PaymentMethod::ZALOPAY], true), 409, 'Unsupported payment method.');
            $target = Status::from($data['status']);
            $expected = Status::from($data['expected_status']);
            $allowed = $target === Status::CANCELLED
                ? in_array($expected, $buyer ? [Status::PENDING, Status::CONFIRMED] : [Status::PENDING, Status::CONFIRMED, Status::PROCESSING], true)
                : (! $buyer && match ($expected) {
                    Status::PENDING => $target === Status::CONFIRMED, Status::CONFIRMED => $target === Status::PROCESSING, Status::PROCESSING => $target === Status::SHIPPING, Status::SHIPPING => $target === Status::COMPLETED, default => false
                });
            abort_unless($allowed, 409, 'Invalid order transition.');
            if ($locked->status === $target) {
                return $locked->load(['orderItems', 'shop', 'order.payments']);
            }
            abort_unless($locked->status === $expected, 409, 'Order status has changed. Refresh before retrying.');
            if ($target === Status::COMPLETED && $order->payment_method === PaymentMethod::COD) {
                abort_unless(($data['cod_collected'] ?? false) === true, 422, 'Confirm full COD collection.');
            }
            $categories = $this->locks->categories();
            $rows = $locked->orderItems()->orderBy('product_id')->get();
            $products = Product::withTrashed()->whereIn('id', $rows->pluck('product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $payments = $order->payments()->orderBy('id')->lockForUpdate()->get();
            if ($order->payment_method === PaymentMethod::ZALOPAY) {
                abort_if($payments->contains(fn ($payment): bool => $payment->awaitsReconciliation()), 409, 'Payment requires reconciliation.');
                $paid = $payments->contains('status', PaymentStatus::PAID);
                abort_if($target === Status::CANCELLED && $paid, 409, 'Paid online orders cannot be cancelled without a refund process.');
                abort_if($target !== Status::CANCELLED && ! $paid, 409, 'Online payment must be verified before fulfillment.');
            }
            if ($target === Status::CANCELLED) {
                abort_if($locked->stock_restored_at !== null, 409, 'Stock was already restored.');
                foreach ($rows as $row) {
                    $product = $products[$row->product_id];
                    abort_if($product->stock_quantity > 4294967295 - $row->quantity, 409, 'Restored stock would exceed the supported limit.');
                    if (! $product->trashed() && $product->status === ProductStatus::SOLD) {
                        $revision = AuditLog::where('entity_type', 'products')->where('entity_id', $product->id)->where('action', 'product.checkout_sold')->latest('id')->value('reason');
                        $shop = $shops[$product->shop_id];
                        $safe = $revision === $product->updated_at->format('Y-m-d H:i:s.u') && $accounts->has($shop->owner_id) && $this->locks->eligible($shop, $accounts[$shop->owner_id], $categories, $product->category_id);
                        $product->status = $safe ? ProductStatus::ACTIVE : ProductStatus::HIDDEN;
                    }
                    $product->stock_quantity += $row->quantity;
                    $product->save();
                }
                $locked->stock_restored_at = now();
                $locked->cancellation_reason = isset($data['failure_category']) ? $data['failure_category'].': '.$data['reason'] : $data['reason'];
            }
            $locked->status = $target;
            $timestamp = match ($target) {
                Status::CONFIRMED => 'confirmed_at', Status::PROCESSING => 'processing_at', Status::SHIPPING => 'shipping_at', Status::COMPLETED => 'completed_at', Status::CANCELLED => 'cancelled_at', default => null
            };
            $locked->$timestamp = now();
            $locked->save();
            $this->audit->record($actor, 'shop_orders', $locked->id, 'shop_order.status_changed', $expected->value, $target->value, $locked->cancellation_reason);
            if ($order->payment_method === PaymentMethod::ZALOPAY) {
                if ($target === Status::CANCELLED) {
                    foreach ($payments as $payment) {
                        if ($payment->status === PaymentStatus::PENDING) {
                            $payment->update(['status' => PaymentStatus::CANCELLED, 'order_url' => null]);
                            $this->audit->record($actor, 'payments', $payment->id, 'payment.cancelled', 'PENDING', 'CANCELLED', 'Online order part cancelled; late money requires reconciliation.');
                        }
                    }
                }

                return $locked->load(['orderItems', 'shop', 'order.payments']);
            }
            $order->setRelation('shopOrders', $parts);
            $amounts = OrderSummary::amounts($order);
            $pending = $payments->firstWhere('status', PaymentStatus::PENDING);
            if ($pending && bccomp($pending->amount, $amounts['payable_amount'], 2) !== 0) {
                $pending->update(['status' => PaymentStatus::CANCELLED]);
                $this->audit->record($actor, 'payments', $pending->id, 'payment.cancelled', 'PENDING', 'CANCELLED', 'Payable amount changed.');
                $pending = null;
            }
            if (! $pending && bccomp($amounts['payable_amount'], '0', 2) > 0 && ! $payments->contains('status', PaymentStatus::PAID)) {
                $pending = $order->payments()->create(['attempt_number' => ($payments->max('attempt_number') ?? 0) + 1, 'method' => PaymentMethod::COD, 'amount' => $amounts['payable_amount'], 'currency' => 'VND', 'status' => PaymentStatus::PENDING, 'transaction_reference' => 'COD-'.Str::uuid()]);
                $this->audit->record($actor, 'payments', $pending->id, 'payment.created', null, 'PENDING', null);
            }
            if ($pending && $amounts['payment_status'] === 'PAID') {
                $pending->update(['status' => PaymentStatus::PAID, 'paid_at' => now()]);
                $this->audit->record($actor, 'payments', $pending->id, 'payment.paid', 'PENDING', 'PAID', 'All remaining COD deliveries collected.');
            }

            return $locked->load(['orderItems', 'shop', 'order.payments']);
        });
    }
}
