<?php

namespace Database\Factories;

use App\Models;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<AuditLog> */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'actor_id' => null,
            'entity_type' => 'orders',
            'entity_id' => Models\Order::factory(),
            'action' => 'order.created',
            'request_id' => (string) Str::uuid(),
            'created_at' => now(),
        ];
    }
}
