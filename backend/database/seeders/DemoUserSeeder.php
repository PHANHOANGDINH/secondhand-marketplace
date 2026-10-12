<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $password = config('demo.password');
        if (! is_string($password) || strlen($password) < 12) {
            throw new RuntimeException('Configure a private DEMO_PASSWORD of at least 12 characters before seeding.');
        }
        $emails = ['admin@example.com', 'seller@example.com', 'buyer@example.com', 'seller2@example.com', 'seller3@example.com', 'seller4@example.com', 'user1@example.com', 'user2@example.com', 'user3@example.com'];
        foreach ($emails as $index => $email) {
            $user = User::withTrashed()->where('email', $email)->first();
            if ($user) {
                continue;
            }
            $user = new User;
            $user->forceFill([
                'name' => 'Demo '.($index + 1), 'email' => $email,
                'password' => $password,
                'role' => $index === 0 ? UserRole::ADMIN : UserRole::USER,
                'status' => UserStatus::ACTIVE,
            ])->save();
            Address::factory()->for($user)->create(['is_default' => true, 'recipient_name' => $user->name]);
        }
    }
}
