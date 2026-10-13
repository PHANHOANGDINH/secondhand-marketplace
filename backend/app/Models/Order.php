<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = ['buyer_id', 'order_code', 'checkout_key', 'request_hash', 'shipping_recipient_name', 'shipping_phone', 'shipping_address_line', 'shipping_ward', 'shipping_district', 'shipping_province', 'shipping_country_code', 'subtotal', 'shipping_fee', 'discount_amount', 'platform_voucher_code', 'total_amount', 'currency', 'payment_method'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id')->withTrashed();
    }

    public function shopOrders(): HasMany
    {
        return $this->hasMany(ShopOrder::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
