<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::ADMIN && $user->status === UserStatus::ACTIVE && ! $user->trashed();
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Category $category): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Category $category): bool
    {
        return $this->viewAny($user);
    }
}
