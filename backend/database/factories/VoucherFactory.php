<?php

namespace Database\Factories;

use App\Models\Shop;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Voucher> */
class VoucherFactory extends Factory
{
    protected $model = Voucher::class;

    public function definition(): array
    {
        return [
            'code' => 'V'.Str::upper(Str::random(9)),
            'scope' => Voucher::PLATFORM,
            'shop_id' => null,
            'type' => Voucher::FIXED,
            'value' => '10000.00',
            'min_order' => '0.00',
            'max_discount' => null,
            'usage_limit' => null,
            'per_user_limit' => null,
            'used_count' => 0,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'status' => Voucher::ACTIVE,
            'created_by' => User::factory(),
        ];
    }

    public function forShop(Shop $shop): static
    {
        return $this->state(fn () => ['scope' => Voucher::SHOP, 'shop_id' => $shop->id, 'created_by' => $shop->owner_id]);
    }

    public function percent(string $value, ?string $max = null): static
    {
        return $this->state(fn () => ['type' => Voucher::PERCENT, 'value' => $value, 'max_discount' => $max]);
    }
}
