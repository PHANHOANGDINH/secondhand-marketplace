<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\ShopOrderStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Records an Admin decision about verified money that arrived when the order could no longer accept it.
 * It never calls the gateway and never moves money: a refund happens outside this application.
 */
class PaymentReconciliationService
{
    public const OUTCOMES = ['REFUNDED_OUTSIDE', 'KEEP_PAID'];

    public function __construct(private OrderLocks $locks, private AuditService $audit) {}

    /** KEEP_PAID is only meaningful while the order can still be fulfilled for exactly this amount. */
    public static function canKeep(Payment $payment, Order $order): bool
    {
        $parts = $order->shopOrders;

        return ! $order->payments->contains(fn (Payment $other): bool => $other->id !== $payment->id && $other->status === PaymentStatus::PAID)
            && $parts->isNotEmpty() && ! $parts->contains('status', ShopOrderStatus::CANCELLED)
            && bccomp($payment->amount, $order->total_amount, 2) === 0;
    }

    public function resolve(User $admin, Payment $payment, array $data): Payment
    {
        return $this->locks->transaction(function () use ($admin, $payment, $data): Payment {
            // Same order as the payment flows: accounts, order, shop orders, payments.
            $accounts = $this->locks->accounts([$admin->id, Order::whereKey($payment->order_id)->value('buyer_id')]);
            $actor = $accounts[$admin->id];
            $order = Order::whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
            $parts = $order->shopOrders()->orderBy('id')->lockForUpdate()->get();
            $payments = $order->payments()->orderBy('id')->lockForUpdate()->get();
            $locked = $payments->firstWhere('id', $payment->id);
            abort_unless($locked, 404);
            Gate::forUser($actor)->authorize('reconcile', $locked);
            abort_unless($locked->reconciliation_required, 409, 'Giao dịch này không cần đối soát.');
            abort_if($locked->reconciliation_resolved_at !== null, 409, 'Giao dịch đã được đối soát. Vui lòng tải lại.');
            $from = $locked->status;
            abort_unless($from->value === $data['expected_status'], 409, 'Trạng thái thanh toán đã thay đổi. Vui lòng tải lại.');
            if ($data['outcome'] === 'KEEP_PAID') {
                $order->setRelation('shopOrders', $parts)->setRelation('payments', $payments);
                abort_unless(self::canKeep($locked, $order), 409, 'Không thể giữ khoản thanh toán này: đơn đã bị hủy, đã có khoản thanh toán khác hoặc số tiền không khớp. Hãy hoàn tiền ngoài hệ thống.');
                $locked->status = PaymentStatus::PAID;
                $locked->paid_at = now();
            } else {
                $locked->status = PaymentStatus::REFUNDED;
            }
            // The flag itself stays as history; resolution is what lifts the fulfilment block.
            $locked->forceFill(['reconciliation_resolved_at' => now(), 'reconciliation_resolved_by' => $actor->id, 'reconciliation_note' => $data['note'], 'order_url' => null])->save();
            $this->audit->record($actor, 'payments', $locked->id, 'payment.reconciliation_resolved', $from->value, $locked->status->value, $data['outcome'].': '.mb_substr($data['note'], 0, 900));

            return $locked;
        });
    }
}
