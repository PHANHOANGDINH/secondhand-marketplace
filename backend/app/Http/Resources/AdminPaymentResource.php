<?php

namespace App\Http\Resources;

use App\Enums\PaymentStatus;
use App\Services\OrderSummary;
use App\Services\PaymentReconciliationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminPaymentResource extends JsonResource
{
    /** Identifiers an Admin needs to find the transaction; never the gateway URL, MAC or raw payload. */
    public function toArray(Request $request): array
    {
        $order = $this->order;
        $open = $this->awaitsReconciliation();

        return [
            'id' => (string) $this->id, 'attempt_number' => $this->attempt_number, 'method' => $this->method->value, 'status' => $this->status->value,
            'amount' => $this->amount, 'currency' => $this->currency,
            'transaction_reference' => $this->transaction_reference, 'gateway_transaction_id' => $this->gateway_transaction_id,
            'failure_code' => $this->failure_code, 'paid_at' => $this->paid_at?->toISOString(), 'failed_at' => $this->failed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(), 'updated_at' => $this->updated_at?->toISOString(),
            'reconciliation_required' => (bool) $this->reconciliation_required, 'reconciliation_open' => $open,
            'reconciliation_resolved_at' => $this->reconciliation_resolved_at?->toISOString(), 'reconciliation_note' => $this->reconciliation_note,
            'reconciliation_resolved_by' => $this->resolver ? ['id' => (string) $this->resolver->id, 'name' => $this->resolver->name] : null,
            'can_resolve' => $open && $request->user()->can('reconcile', $this->resource),
            'can_keep_paid' => $open && PaymentReconciliationService::canKeep($this->resource, $order),
            'order' => [
                'id' => (string) $order->id, 'order_code' => $order->order_code, 'total_amount' => $order->total_amount, 'overall_status' => OrderSummary::overallStatus($order),
                'buyer' => ['id' => (string) $order->buyer_id, 'name' => $order->buyer && ! $order->buyer->trashed() ? $order->buyer->name : 'Người mua'],
                'shop_order_statuses' => $order->shopOrders->map(fn ($part): string => $part->status->value)->values()->all(),
                'has_other_paid_payment' => $order->payments->contains(fn ($other): bool => $other->id !== $this->id && $other->status === PaymentStatus::PAID),
            ],
        ];
    }
}
