<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return ! $actor->trashed() && $actor->role === UserRole::ADMIN && $actor->status === UserStatus::ACTIVE;
    }

    public function moderate(User $actor, User $target): bool
    {
        return $this->viewAny($actor) && ! $target->trashed() && $actor->id !== $target->id;
    }
}
