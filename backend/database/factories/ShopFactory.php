<?php

namespace Database\Factories;

use App\Enums;
use App\Models;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Shop> */
class ShopFactory extends Factory
{
    protected $model = Shop::class;

    public function definition(): array
    {
        return [
            'owner_id' => Models\User::factory(),
            'name' => fake()->company(),
            'slug' => 'shop-'.Str::uuid(),
            'description' => 'Local development shop',
            'status' => Enums\ShopStatus::ACTIVE,
        ];
    }
}
