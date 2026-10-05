<?php

namespace Database\Factories;

use App\Models;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductImage> */
class ProductImageFactory extends Factory
{
    protected $model = ProductImage::class;

    public function definition(): array
    {
        return [
            'product_id' => Models\Product::factory(),
            'image_path' => 'placeholders/product.svg',
            'alt_text' => 'Local development placeholder',
            'is_primary' => false,
            'sort_order' => 0,
        ];
    }
}
