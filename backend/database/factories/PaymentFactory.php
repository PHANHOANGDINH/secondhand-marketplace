<?php

namespace Database\Factories;

use App\Enums;
use App\Models;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'order_id' => Models\Order::factory(),
            'attempt_number' => 1,
            'method' => fn (array $a) => Models\Order::findOrFail($a['order_id'])->payment_method,
            'amount' => fn (array $a) => Models\Order::findOrFail($a['order_id'])->total_amount,
            'currency' => 'VND',
            'status' => Enums\PaymentStatus::PENDING,
            'transaction_reference' => 'PAY-'.Str::uuid(),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => ['status' => Enums\PaymentStatus::PAID, 'paid_at' => now()]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['status' => Enums\PaymentStatus::FAILED, 'failed_at' => now(), 'failure_code' => 'DEMO_FAILURE']);
    }
}
