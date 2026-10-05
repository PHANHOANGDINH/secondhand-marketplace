<?php

namespace App\Policies;

use App\Enums\ShopStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Shop;
use App\Models\User;

class ShopPolicy
{
    public function create(User $user): bool
    {
        return $user->status === UserStatus::ACTIVE && ! $user->trashed();
    }

    public function view(User $user, Shop $shop): bool
    {
        return $this->create($user) && $user->id === $shop->owner_id && ! $shop->trashed();
    }

    public function update(User $user, Shop $shop): bool
    {
        return $this->view($user, $shop) && $shop->status !== ShopStatus::SUSPENDED;
    }

    public function submit(User $user, Shop $shop): bool
    {
        return $this->update($user, $shop);
    }

    public function viewAny(User $user): bool
    {
        return $this->create($user) && $user->role === UserRole::ADMIN;
    }

    public function viewAdmin(User $user, Shop $shop): bool
    {
        return $this->viewAny($user);
    }

    public function moderate(User $user, Shop $shop): bool
    {
        return $this->viewAny($user) && $user->id !== $shop->owner_id && ! $shop->trashed();
    }
}
