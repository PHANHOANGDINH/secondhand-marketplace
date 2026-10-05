<?php

namespace Database\Factories;

use App\Enums;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Category> */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'slug' => 'category-'.Str::uuid(),
            'parent_id' => null,
            'status' => Enums\CategoryStatus::ACTIVE,
        ];
    }
}
