<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $emails = ['admin@example.com', 'seller@example.com', 'buyer@example.com', 'seller2@example.com', 'seller3@example.com', 'seller4@example.com', 'user1@example.com', 'user2@example.com', 'user3@example.com'];
        foreach ($emails as $index => $email) {
            $user = User::withTrashed()->where('email', $email)->first();
            if ($user) {
                continue;
            }
            $user = new User;
            $user->forceFill([
                'name' => 'Demo '.($index + 1), 'email' => $email,
                'password' => 'LocalDemoOnly2026!',
                'role' => $index === 0 ? UserRole::ADMIN : UserRole::USER,
                'status' => UserStatus::ACTIVE,
            ])->save();
            Address::factory()->for($user)->create(['is_default' => true, 'recipient_name' => $user->name]);
        }
    }
}
