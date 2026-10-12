<?php

namespace Database\Factories;

use App\Models\ProductVideo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class VideoCommentFactory extends Factory
{
    public function definition(): array
    {
        return ['product_video_id' => ProductVideo::factory(), 'user_id' => User::factory(), 'body' => 'Sản phẩm còn hoạt động tốt không?', 'status' => 'PUBLISHED'];
    }
}
