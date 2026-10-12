<?php

namespace App\Policies;

use App\Enums\UserStatus;
use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    public function create(User $user): bool
    {
        return ! $user->trashed() && $user->status === UserStatus::ACTIVE;
    }

    public function view(User $user, Conversation $conversation): bool
    {
        return $this->create($user) && $conversation->participants()->where('user_id', $user->id)->exists();
    }

    public function send(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }
}
