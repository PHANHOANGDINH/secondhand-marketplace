<?php

namespace Database\Factories;

use App\Models;
use App\Models\Cart;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Cart> */
class CartFactory extends Factory
{
    protected $model = Cart::class;

    public function definition(): array
    {
        return [
            'user_id' => Models\User::factory(),
        ];
    }
}
