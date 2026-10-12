<?php

namespace Database\Factories;

use App\Enums\AuctionStatus;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuctionFactory extends Factory
{
    public function definition(): array
    {
        return ['product_id' => Product::factory()->active(), 'seller_id' => fn (array $attributes): int => Product::findOrFail($attributes['product_id'])->shop->owner_id, 'start_price' => '100.00', 'current_price' => '100.00', 'bid_increment' => '10.00', 'starts_at' => now()->subMinute(), 'ends_at' => now()->addHour(), 'status' => AuctionStatus::LIVE];
    }
}
