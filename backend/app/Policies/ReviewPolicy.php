<?php

namespace App\Policies;

use App\Enums\UserStatus;
use App\Models\OrderItem;
use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    public function create(User $user, OrderItem $item): bool
    {
        return ! $user->trashed() && $user->status === UserStatus::ACTIVE && $item->shopOrder->order->buyer_id === $user->id;
    }

    public function update(User $user, Review $review): bool
    {
        return ! $user->trashed() && $user->status === UserStatus::ACTIVE && $review->user_id === $user->id;
    }

    public function viewAdmin(User $user): bool
    {
        return (new UserPolicy)->viewAny($user);
    }

    public function moderate(User $user, Review $review): bool
    {
        return $this->viewAdmin($user);
    }
}
