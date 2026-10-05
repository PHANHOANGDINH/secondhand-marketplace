<?php

namespace Database\Factories;

use App\Enums;
use App\Models;
use App\Models\ShopOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ShopOrder> */
class ShopOrderFactory extends Factory
{
    protected $model = ShopOrder::class;

    public function definition(): array
    {
        return [
            'order_id' => Models\Order::factory(),
            'shop_id' => Models\Shop::factory(),
            'status' => Enums\ShopOrderStatus::PENDING,
            'subtotal' => fn (array $a) => Models\Order::findOrFail($a['order_id'])->subtotal,
            'shipping_fee' => '0.00',
            'discount_amount' => '0.00',
            'total_amount' => fn (array $a) => bcsub(bcadd((string) $a['subtotal'], (string) $a['shipping_fee'], 2), (string) $a['discount_amount'], 2),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => Enums\ShopOrderStatus::COMPLETED,
            'confirmed_at' => now()->subDays(3),
            'processing_at' => now()->subDays(2),
            'shipping_at' => now()->subDay(),
            'completed_at' => now(),
        ]);
    }
}
