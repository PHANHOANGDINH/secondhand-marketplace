<?php

namespace Database\Factories;

use App\Enums\ReviewStatus;
use App\Models;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Models\ShopReview> */
class ShopReviewFactory extends Factory
{
    public function definition(): array
    {
        return ['shop_id' => Models\Shop::factory(), 'user_id' => Models\User::factory(), 'rating' => 5, 'comment' => 'Gian hàng uy tín, giao đúng mô tả.', 'status' => ReviewStatus::PUBLISHED];
    }
}
