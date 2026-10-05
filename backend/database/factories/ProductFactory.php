<?php

namespace Database\Factories;

use App\Enums;
use App\Models;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'shop_id' => Models\Shop::factory(),
            'category_id' => Models\Category::factory(),
            'name' => fake()->words(3, true),
            'slug' => 'product-'.Str::uuid(),
            'description' => 'Used item with condition described for local testing.',
            'price' => '100000.00',
            'stock_quantity' => 5,
            'condition' => Enums\ProductCondition::GOOD,
            'status' => Enums\ProductStatus::DRAFT,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => Enums\ProductStatus::ACTIVE, 'published_at' => now()]);
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Product $product): void {
            Models\ProductImage::factory()->for($product)->create(['is_primary' => true]);
        });
    }
}
