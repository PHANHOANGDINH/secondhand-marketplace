<?php

namespace App\Policies;

use App\Enums\UserStatus;
use App\Models\Address;
use App\Models\User;

class AddressPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->status === UserStatus::ACTIVE && ! $user->trashed();
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(User $user, Address $address): bool
    {
        return $this->viewAny($user) && $user->id === $address->user_id;
    }

    public function update(User $user, Address $address): bool
    {
        return $this->view($user, $address);
    }

    public function delete(User $user, Address $address): bool
    {
        return $this->view($user, $address);
    }

    public function setDefault(User $user, Address $address): bool
    {
        return $this->view($user, $address);
    }
}
