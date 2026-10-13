<?php

namespace App\Models;

use App\Enums\ShopOrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShopOrder extends Model
{
    use HasFactory;

    protected $fillable = ['order_id', 'shop_id', 'status', 'subtotal', 'shipping_fee', 'discount_amount', 'discount_shop', 'discount_platform', 'voucher_code', 'total_amount', 'confirmed_at', 'processing_at', 'shipping_at', 'completed_at', 'cancelled_at', 'cancellation_reason', 'stock_restored_at'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'status' => ShopOrderStatus::class,
            'confirmed_at' => 'datetime',
            'processing_at' => 'datetime',
            'shipping_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'stock_restored_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class)->withTrashed();
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
