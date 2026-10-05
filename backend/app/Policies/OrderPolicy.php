<?php

namespace App\Policies;

use App\Enums\UserStatus;
use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return ! $user->trashed() && $user->status === UserStatus::ACTIVE && $order->buyer_id === $user->id;
    }

    public function viewAdmin(User $user): bool
    {
        return (new UserPolicy)->viewAny($user);
    }
}
