<?php

namespace Database\Factories;

use App\Models;
use App\Models\CartItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CartItem> */
class CartItemFactory extends Factory
{
    protected $model = CartItem::class;

    public function definition(): array
    {
        return [
            'cart_id' => Models\Cart::factory(),
            'product_id' => Models\Product::factory()->active(),
            'quantity' => 1,
        ];
    }
}
