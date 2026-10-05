<?php

namespace Database\Factories;

use App\Enums;
use App\Models;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Order> */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'buyer_id' => Models\User::factory(),
            'order_code' => 'SHM-'.Str::upper(Str::random(20)),
            'checkout_key' => (string) Str::uuid(),
            'request_hash' => hash('sha256', Str::random(32)),
            'shipping_recipient_name' => fake()->name(),
            'shipping_phone' => '0901234567',
            'shipping_address_line' => '10 Nguyen Trai',
            'shipping_ward' => 'Phuong 1',
            'shipping_district' => null,
            'shipping_province' => 'Ho Chi Minh',
            'shipping_country_code' => 'VN',
            'subtotal' => '100000.00',
            'shipping_fee' => '0.00',
            'discount_amount' => '0.00',
            'total_amount' => fn (array $a) => bcsub(bcadd((string) $a['subtotal'], (string) $a['shipping_fee'], 2), (string) $a['discount_amount'], 2),
            'currency' => 'VND',
            'payment_method' => Enums\PaymentMethod::COD,
        ];
    }
}
