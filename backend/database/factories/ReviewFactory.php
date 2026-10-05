<?php

namespace Database\Factories;

use App\Enums;
use App\Models;
use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Review> */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    public function definition(): array
    {
        return [
            'order_item_id' => Models\OrderItem::factory()->for(Models\ShopOrder::factory()->completed()),
            'user_id' => fn (array $a) => Models\OrderItem::findOrFail($a['order_item_id'])->shopOrder->order->buyer_id,
            'product_id' => fn (array $a) => Models\OrderItem::findOrFail($a['order_item_id'])->product_id,
            'rating' => 5,
            'comment' => 'Item matches the description.',
            'status' => Enums\ReviewStatus::PUBLISHED,
        ];
    }
}
