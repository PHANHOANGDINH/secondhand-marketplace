<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ShopOrderStatus as Status;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;

class OrderSummary
{
    public const RELATIONS = ['shopOrders.shop', 'shopOrders.orderItems.review', 'payments'];

    public static function overallStatus(Order $order): string
    {
        $parts = $order->shopOrders;
        $statuses = $parts->pluck('status')->unique();

        return match (true) {
            $parts->isEmpty() => 'PENDING',
            $statuses->count() === 1 && $statuses->first() === Status::CANCELLED => 'CANCELLED',
            $statuses->count() === 1 && $statuses->first() === Status::COMPLETED => 'COMPLETED',
            $parts->every(fn ($part) => in_array($part->status, [Status::COMPLETED, Status::CANCELLED], true)) => 'PARTIALLY_COMPLETED',
            $statuses->count() === 1 && $statuses->first() === Status::PENDING => 'PENDING',
            default => 'IN_PROGRESS',
        };
    }

    public static function amounts(Order $order): array
    {
        $parts = $order->shopOrders;
        $overall = self::overallStatus($order);
        $payable = '0.00';
        $collected = '0.00';
        foreach ($parts as $part) {
            if ($part->status !== Status::CANCELLED) {
                $payable = bcadd($payable, $part->total_amount, 2);
            }
            if ($part->status === Status::COMPLETED) {
                $collected = bcadd($collected, $part->total_amount, 2);
            }
        }

        if ($order->payment_method === PaymentMethod::ZALOPAY) {
            $payments = $order->payments;
            $paid = $payments->contains('status', PaymentStatus::PAID);
            $status = $paid ? 'PAID' : ($payments->sortBy('attempt_number')->last()?->status->value ?? 'PENDING');

            return ['overall_status' => $overall, 'payable_amount' => $payable, 'cod_collected_amount' => '0.00', 'amount_due' => $paid ? '0.00' : $payable, 'payment_status' => $status];
        }

        return ['overall_status' => $overall, 'payable_amount' => $payable, 'cod_collected_amount' => $collected,
            'amount_due' => bcsub($payable, $collected, 2),
            'payment_status' => bccomp($payable, '0', 2) === 0 ? 'CANCELLED' : (bccomp($collected, $payable, 2) === 0 ? 'PAID' : 'PENDING')];
    }

    public static function filter(Builder $query, ?string $status): Builder
    {
        if ($status === null) {
            return $query;
        }
        if (in_array($status, ['PENDING', 'COMPLETED', 'CANCELLED'], true)) {
            return $query->whereHas('shopOrders')->whereDoesntHave('shopOrders', fn (Builder $parts) => $parts->where('status', '!=', $status));
        }
        if ($status === 'PARTIALLY_COMPLETED') {
            return $query->whereHas('shopOrders', fn (Builder $parts) => $parts->where('status', 'COMPLETED'))
                ->whereHas('shopOrders', fn (Builder $parts) => $parts->where('status', 'CANCELLED'))
                ->whereDoesntHave('shopOrders', fn (Builder $parts) => $parts->whereNotIn('status', ['COMPLETED', 'CANCELLED']));
        }

        return $query->whereHas('shopOrders', fn (Builder $parts) => $parts->where('status', '!=', 'PENDING'))
            ->whereHas('shopOrders', fn (Builder $parts) => $parts->whereNotIn('status', ['COMPLETED', 'CANCELLED']));
    }

    public static function checked(string $amount): string
    {
        abort_if(bccomp($amount, '9999999999999.99', 2) > 0, 422, 'Order amount exceeds the supported limit.');

        return $amount;
    }

    public static function shipping(Order $order): array
    {
        $result = [];
        foreach (['recipient_name', 'phone', 'address_line', 'ward', 'district', 'province', 'country_code'] as $field) {
            $result[$field] = $order->{'shipping_'.$field};
        }

        return $result;
    }
}
