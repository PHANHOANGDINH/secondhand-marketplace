<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = ['order_id', 'attempt_number', 'method', 'amount', 'currency', 'status', 'transaction_reference', 'gateway_transaction_id', 'paid_at', 'failed_at', 'failure_code', 'request_key', 'order_url', 'preferred_payment_method', 'gateway_app_id', 'gateway_app_time', 'gateway_requested_at', 'expires_at', 'reconciliation_required'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected function casts(): array
    {
        return [
            'preferred_payment_method' => 'array', 'gateway_app_id' => 'integer', 'gateway_app_time' => 'integer', 'gateway_requested_at' => 'datetime', 'expires_at' => 'datetime', 'reconciliation_required' => 'boolean',
            'attempt_number' => 'integer',
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
