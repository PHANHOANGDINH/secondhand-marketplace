<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Conversation> */
class ConversationFactory extends Factory
{
    public function definition(): array
    {
        return ['buyer_id' => User::factory(), 'seller_id' => User::factory(), 'context_key' => (string) Str::uuid()];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Conversation $conversation): void {
            foreach ([$conversation->buyer_id, $conversation->seller_id] as $id) {
                $participant = $conversation->participants()->make();
                $participant->forceFill(['user_id' => $id, 'last_read_message_id' => 0])->save();
            }
        });
    }
}
