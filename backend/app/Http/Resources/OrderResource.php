<?php

namespace App\Http\Resources;

use App\Services\OrderSummary;
use App\Services\ZaloPayService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        foreach ($this->shopOrders as $part) {
            $part->setRelation('order', $this->resource);
        }

        return ['can_retry_payment' => ZaloPayService::canRetry($this->resource), 'reconciliation_required' => $this->payments->contains(fn ($payment): bool => $payment->awaitsReconciliation()), 'id' => (string) $this->id, 'order_code' => $this->order_code, 'subtotal' => $this->subtotal, 'shipping_fee' => $this->shipping_fee, 'discount_amount' => $this->discount_amount, 'platform_voucher_code' => $this->platform_voucher_code, 'total_amount' => $this->total_amount, 'currency' => $this->currency, 'payment_method' => $this->payment_method->value, 'shipping_address' => OrderSummary::shipping($this->resource), 'shop_orders' => ShopOrderResource::collection($this->shopOrders), 'payments' => PaymentResource::collection($this->payments), ...OrderSummary::amounts($this->resource), 'created_at' => $this->created_at?->toISOString()];
    }
}
