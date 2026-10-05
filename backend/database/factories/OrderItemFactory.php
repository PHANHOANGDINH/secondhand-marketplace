<?php

namespace Database\Factories;

use App\Models;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrderItem> */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        return [
            'shop_order_id' => Models\ShopOrder::factory(),
            'product_id' => fn (array $a) => Models\Product::factory()->active()->create([
                'shop_id' => Models\ShopOrder::findOrFail($a['shop_order_id'])->shop_id,
                'price' => Models\ShopOrder::findOrFail($a['shop_order_id'])->subtotal,
            ])->id,
            'product_name' => fn (array $a) => Models\Product::findOrFail($a['product_id'])->name,
            'product_image' => fn (array $a) => Models\Product::findOrFail($a['product_id'])->images()->where('is_primary', true)->value('image_path'),
            'product_price' => fn (array $a) => Models\Product::findOrFail($a['product_id'])->price,
            'quantity' => 1,
            'subtotal' => fn (array $a) => bcmul((string) $a['product_price'], (string) $a['quantity'], 2),
        ];
    }
}
