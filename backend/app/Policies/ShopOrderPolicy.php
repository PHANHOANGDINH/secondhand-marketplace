<?php

namespace App\Policies;

use App\Enums\UserStatus;
use App\Models\ShopOrder;
use App\Models\User;

class ShopOrderPolicy
{
    public function view(User $user, ShopOrder $part): bool
    {
        return ! $user->trashed() && $user->status === UserStatus::ACTIVE && $part->shop->owner_id === $user->id;
    }

    public function update(User $user, ShopOrder $part): bool
    {
        return $this->view($user, $part);
    }

    public function cancel(User $user, ShopOrder $part): bool
    {
        return ! $user->trashed() && $user->status === UserStatus::ACTIVE && $part->order->buyer_id === $user->id;
    }
}
