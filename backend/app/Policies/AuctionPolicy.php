<?php

namespace App\Policies;

use App\Enums\UserStatus;
use App\Models\Auction;
use App\Models\User;

class AuctionPolicy
{
    private function active(User $user): bool
    {
        return $user->status === UserStatus::ACTIVE && ! $user->trashed();
    }

    public function create(User $user): bool
    {
        return $this->active($user);
    }

    public function viewAny(User $user): bool
    {
        return $this->active($user);
    }

    public function view(User $user, Auction $auction): bool
    {
        return $this->active($user) && $user->id === $auction->seller_id;
    }

    public function cancel(User $user, Auction $auction): bool
    {
        return $this->view($user, $auction);
    }

    public function checkout(User $user, Auction $auction): bool
    {
        return $this->active($user) && $user->id === $auction->winner_user_id && $user->id !== $auction->seller_id;
    }

    public function bid(User $user, Auction $auction): bool
    {
        return $this->active($user) && $user->id !== $auction->seller_id;
    }
}
