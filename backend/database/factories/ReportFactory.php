<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReportFactory extends Factory
{
    public function definition(): array
    {
        return ['reporter_user_id' => User::factory(), 'target_type' => 'PRODUCT', 'target_id' => Product::factory()->active(), 'target_name' => 'Sản phẩm thử nghiệm', 'reason' => 'MISLEADING', 'status' => 'PENDING'];
    }
}
