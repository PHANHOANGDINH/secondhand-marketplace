<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVideo;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductVideoFactory extends Factory
{
    protected $model = ProductVideo::class;

    public function definition(): array
    {
        return ['product_id' => Product::factory(), 'uploaded_by' => fn (array $data) => Product::findOrFail($data['product_id'])->shop->owner_id, 'title' => 'Video review', 'path' => fn (array $data) => 'product-videos/'.$data['product_id'].'/'.Str::uuid().'.webm', 'mime_type' => 'video/webm', 'file_size' => 1000, 'status' => 'READY', 'sort_order' => 0, 'is_primary' => true];
    }
}
