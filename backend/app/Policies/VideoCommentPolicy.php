<?php

namespace App\Policies;

use App\Enums\ShopStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\ProductVideo;
use App\Models\User;
use App\Models\VideoComment;

class VideoCommentPolicy
{
    public function create(User $user, ProductVideo $video): bool
    {
        return $user->status === UserStatus::ACTIVE && ! $user->trashed();
    }

    public function hide(User $user, VideoComment $comment): bool
    {
        if ($user->status !== UserStatus::ACTIVE || $user->trashed()) {
            return false;
        }
        if ($user->role === UserRole::ADMIN || $user->id === $comment->user_id) {
            return true;
        }
        $shop = $comment->video?->product?->shop;

        return $shop && ! $shop->trashed() && $shop->status === ShopStatus::ACTIVE && $shop->owner_id === $user->id;
    }
}
