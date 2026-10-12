<?php

namespace Database\Factories;

use App\Models\Auction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BidFactory extends Factory
{
    public function definition(): array
    {
        return ['auction_id' => Auction::factory(), 'user_id' => User::factory(), 'amount' => '100.00'];
    }
}
