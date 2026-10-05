<?php

namespace Database\Factories;

use App\Models;
use App\Models\Address;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Address> */
class AddressFactory extends Factory
{
    protected $model = Address::class;

    public function definition(): array
    {
        return [
            'user_id' => Models\User::factory(),
            'recipient_name' => fake()->name(),
            'phone' => '0901234567',
            'address_line' => '10 Nguyen Trai',
            'ward' => 'Phuong 1',
            'district' => null,
            'province' => 'Ho Chi Minh',
            'country_code' => 'VN',
            'is_default' => false,
        ];
    }
}
