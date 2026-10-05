<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ShopOrderStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ZaloPayService
{
    public function __construct(private ZaloPayGateway $gateway, private AuditService $audit, private OrderLocks $locks) {}

    /** Call only while holding the Order lock (or creating the Order). */
    public function newAttempt(Order $order, int $number, array $channels = [], ?string $key = null): Payment
    {
        $this->gateway->configured();
        ZaloPayGateway::amount($order->total_amount);

        return $order->payments()->create(['attempt_number' => $number, 'method' => PaymentMethod::ZALOPAY, 'status' => PaymentStatus::PENDING, 'amount' => $order->total_amount, 'currency' => 'VND', 'transaction_reference' => now('Asia/Ho_Chi_Minh')->format('ymd').'_'.str_replace('-', '', (string) Str::uuid()), 'request_key' => $key, 'preferred_payment_method' => $channels, 'gateway_app_id' => (int) config('zalopay.app_id'), 'gateway_app_time' => now()->getTimestampMs(), 'expires_at' => now()->addSeconds(config('zalopay.expire_seconds'))]);
    }

    public function start(Payment $payment): void
    {
        $this->gateway->configured();
        $claimed = $this->locks->transaction(function () use ($payment): ?Payment {
            $this->locks->accounts([Order::whereKey($payment->order_id)->value('buyer_id')]);
            $order = Order::whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
            $locked = $order->payments()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== PaymentStatus::PENDING || $locked->gateway_requested_at || $locked->expires_at->isPast()) {
                return null;
            }
            if ($order->payments()->where('reconciliation_required', true)->exists() || $order->payments()->where('status', PaymentStatus::PAID)->exists() || $order->shopOrders()->where('status', '!=', ShopOrderStatus::PENDING)->exists()) {
                return null;
            }
            $locked->update(['gateway_requested_at' => now()]);

            return $locked->setRelation('order', $order);
        });
        if (! $claimed) {
            return;
        }
        // External HTTP never runs in a database transaction or a deadlock retry.
        $result = $this->gateway->create($claimed);
        $this->locks->transaction(function () use ($claimed, $result): void {
            $this->locks->accounts([Order::whereKey($claimed->order_id)->value('buyer_id')]);
            $order = Order::whereKey($claimed->order_id)->lockForUpdate()->firstOrFail();
            $locked = $order->payments()->whereKey($claimed->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== PaymentStatus::PENDING) {
                return;
            }
            if (($result['return_code'] ?? null) === 1 && ZaloPayGateway::safeUrl($result['order_url'] ?? null)) {
                $locked->update(['order_url' => $result['order_url']]);
            } elseif (($result['return_code'] ?? null) === 2 && in_array($result['sub_return_code'] ?? null, [-401, -402, -429], true)) {
                $this->fail($locked, 'CREATE_REJECTED');
            }
        });
    }

    private function fail(Payment $payment, string $code): void
    {
        $payment->update(['status' => PaymentStatus::FAILED, 'failed_at' => now(), 'failure_code' => $code, 'order_url' => null]);
        $this->audit->record($payment->order->buyer, 'payments', $payment->id, 'payment.failed', 'PENDING', 'FAILED', $code);
    }

    public function callback(array $input): int
    {
        $data = $this->gateway->callbackData($input);
        if (! $data) {
            return -1;
        }
        $reference = $data['app_trans_id'] ?? null;
        if (! is_string($reference)) {
            return -1;
        }
        $payment = Payment::where('method', PaymentMethod::ZALOPAY)->where('transaction_reference', $reference)->first();
        if (! $payment || (string) ($data['app_id'] ?? '') !== (string) $payment->gateway_app_id || ($data['app_user'] ?? '') !== 'buyer-'.$payment->order->buyer_id || (string) ($data['app_time'] ?? '') !== (string) $payment->gateway_app_time) {
            return -1;
        }

        return $this->confirm($payment, $data);
    }

    public function sync(Payment $payment): Payment
    {
        if ($payment->status === PaymentStatus::PENDING && ! $payment->gateway_requested_at && $payment->expires_at?->isPast()) {
            $this->locks->transaction(function () use ($payment): void {
                $this->locks->accounts([Order::whereKey($payment->order_id)->value('buyer_id')]);
                $order = Order::whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
                $locked = $order->payments()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
                if ($locked->status === PaymentStatus::PENDING && ! $locked->gateway_requested_at && $locked->expires_at->isPast()) {
                    $this->fail($locked, 'NOT_SENT_EXPIRED');
                }
            });

            return $payment->refresh();
        }
        if ($payment->method !== PaymentMethod::ZALOPAY || $payment->status === PaymentStatus::PAID || $payment->reconciliation_required || ! $payment->gateway_requested_at) {
            return $payment;
        }
        $result = $this->gateway->query($payment);
        if (($result['return_code'] ?? null) === 1) {
            $this->confirm($payment, $result);
        } elseif (($result['return_code'] ?? null) === 2 && ($result['is_processing'] ?? true) === false && in_array($result['sub_return_code'] ?? null, [-54, -101], true) && $payment->expires_at?->copy()->addMinute()->isPast()) {
            $this->locks->transaction(function () use ($payment): void {
                $this->locks->accounts([Order::whereKey($payment->order_id)->value('buyer_id')]);
                $order = Order::whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
                $locked = $order->payments()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
                if ($locked->status === PaymentStatus::PENDING) {
                    $this->fail($locked, 'GATEWAY_EXPIRED');
                }
            });
        }

        return $payment->refresh();
    }

    private function confirm(Payment $payment, array $data): int
    {
        $amount = $data['amount'] ?? null;
        $transaction = $data['zp_trans_id'] ?? null;
        if ((! is_int($amount) && ! is_string($amount)) || ! preg_match('/^[0-9]+$/D', (string) $amount) || bccomp((string) $amount, $payment->amount, 2) !== 0 || (! is_string($transaction) && ! is_int($transaction)) || ! preg_match('/^[1-9][0-9]{0,99}$/D', (string) $transaction)) {
            return -1;
        }
        if (isset($data['currency']) && $data['currency'] !== 'VND') {
            return -1;
        }
        if (isset($data['app_id']) && (string) $data['app_id'] !== (string) $payment->gateway_app_id) {
            return -1;
        }
        if (isset($data['app_trans_id']) && $data['app_trans_id'] !== $payment->transaction_reference) {
            return -1;
        }
        try {
            return $this->locks->transaction(function () use ($payment, $transaction): int {
                $this->locks->accounts([Order::whereKey($payment->order_id)->value('buyer_id')]);
                $order = Order::whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
                $parts = $order->shopOrders()->orderBy('id')->lockForUpdate()->get();
                $payments = $order->payments()->orderBy('id')->lockForUpdate()->get();
                $locked = $payments->firstWhere('id', $payment->id);
                if ($locked->gateway_transaction_id) {
                    return hash_equals($locked->gateway_transaction_id, (string) $transaction) ? 1 : -1;
                }
                if (Payment::where('method', PaymentMethod::ZALOPAY)->where('gateway_transaction_id', (string) $transaction)->exists()) {
                    return -1;
                }
                $safe = $locked->status === PaymentStatus::PENDING && ! $payments->contains('status', PaymentStatus::PAID) && ! $payments->contains('reconciliation_required', true) && $parts->isNotEmpty() && $parts->every(fn ($part): bool => $part->status === ShopOrderStatus::PENDING) && bccomp($locked->amount, $order->total_amount, 2) === 0;
                $locked->gateway_transaction_id = (string) $transaction;
                if (! $safe) {
                    foreach ($payments as $other) {
                        if ($other->id !== $locked->id && $other->status === PaymentStatus::PENDING) {
                            $other->update(['status' => PaymentStatus::CANCELLED, 'order_url' => null]);
                            $this->audit->record($order->buyer, 'payments', $other->id, 'payment.cancelled', 'PENDING', 'CANCELLED', 'Late verified money on an earlier attempt; reconciliation required.');
                        }
                    }
                    $locked->reconciliation_required = true;
                    $locked->order_url = null;
                    $locked->save();
                    $this->audit->record($order->buyer, 'payments', $locked->id, 'payment.reconciliation_required', $locked->status->value, $locked->status->value, 'Verified money after cancellation or a terminal attempt. Manual reconciliation required.');

                    return 1;
                }
                $locked->status = PaymentStatus::PAID;
                $locked->paid_at = now();
                $locked->order_url = null;
                $locked->save();
                $this->audit->record($order->buyer, 'payments', $locked->id, 'payment.paid', 'PENDING', 'PAID', 'Verified ZaloPay result.');

                return 1;
            });
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                return -1;
            }
            throw $exception;
        }
    }

    public function retry(User $user, Order $order, string $key, array $channels): Payment
    {
        Gate::forUser($user)->authorize('view', $order);
        abort_unless($order->payment_method === PaymentMethod::ZALOPAY, 409, 'This order does not use ZaloPay.');
        $prior = $order->payments()->where('request_key', $key)->first();
        if (! $prior) {
            $latest = $order->payments()->latest('attempt_number')->firstOrFail();
            $this->sync($latest);
        }
        $payment = $this->locks->transaction(function () use ($user, $order, $key, $channels): Payment {
            $actor = $this->locks->accounts([$user->id])[$user->id];
            $this->locks->active($actor);
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('view', $locked);
            $parts = $locked->shopOrders()->orderBy('id')->lockForUpdate()->get();
            $payments = $locked->payments()->orderBy('attempt_number')->lockForUpdate()->get();
            if ($prior = $payments->firstWhere('request_key', $key)) {
                abort_unless(($prior->preferred_payment_method ?? []) === $channels, 409, 'Idempotency key has a different payload.');

                return $prior;
            }
            abort_if($payments->contains('status', PaymentStatus::PAID) || $payments->contains('reconciliation_required', true), 409, 'Order is paid or requires reconciliation.');
            abort_unless($parts->isNotEmpty() && $parts->every(fn ($part): bool => $part->status === ShopOrderStatus::PENDING), 409, 'A cancelled or progressing order cannot start another payment.');
            abort_unless($payments->last()?->status === PaymentStatus::FAILED, 409, 'Previous payment is still unresolved. Query its status before retrying.');
            $new = $this->newAttempt($locked, ($payments->max('attempt_number') ?? 0) + 1, $channels, $key);
            $this->audit->record($actor, 'payments', $new->id, 'payment.created', null, 'PENDING', 'ZaloPay retry; stock unchanged.');

            return $new;
        });
        $this->start($payment);

        return $payment->refresh();
    }

    public static function canRetry(Order $order): bool
    {
        $payments = $order->payments;

        return $order->payment_method === PaymentMethod::ZALOPAY && $payments->sortBy('attempt_number')->last()?->status === PaymentStatus::FAILED && ! $payments->contains('status', PaymentStatus::PAID) && ! $payments->contains('reconciliation_required', true) && $order->shopOrders->isNotEmpty() && $order->shopOrders->every(fn ($part): bool => $part->status === ShopOrderStatus::PENDING);
    }
}
