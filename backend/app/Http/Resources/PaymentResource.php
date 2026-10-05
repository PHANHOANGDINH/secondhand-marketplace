<?php

namespace App\Http\Resources;

use App\Enums\PaymentStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['order_url' => $this->status === PaymentStatus::PENDING && ! $this->reconciliation_required && $this->expires_at?->isFuture() ? $this->order_url : null, 'expires_at' => $this->expires_at?->toISOString(), 'reconciliation_required' => (bool) $this->reconciliation_required, 'id' => (string) $this->id, 'attempt_number' => $this->attempt_number, 'method' => $this->method->value, 'status' => $this->status->value, 'amount' => $this->amount, 'currency' => $this->currency, 'paid_at' => $this->paid_at?->toISOString(), 'created_at' => $this->created_at?->toISOString()];
    }
}
