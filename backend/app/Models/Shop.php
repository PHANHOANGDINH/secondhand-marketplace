<?php

namespace App\Models;

use App\Enums\ShopStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shop extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['owner_id', 'name', 'slug', 'description', 'province_code', 'logo_path', 'status', 'moderation_reason'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected function casts(): array
    {
        return [
            'status' => ShopStatus::class,
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** Shop vouchers a buyer could use right now: active, within their dates and not used up. */
    public function activeVouchers(): HasMany
    {
        return $this->hasMany(Voucher::class)->where('scope', Voucher::SHOP)->where('status', Voucher::ACTIVE)
            ->where('starts_at', '<=', now())->where('ends_at', '>', now())
            ->where(fn ($query) => $query->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit'));
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function shopOrders(): HasMany
    {
        return $this->hasMany(ShopOrder::class);
    }
}
