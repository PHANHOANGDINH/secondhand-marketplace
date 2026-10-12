<?php

namespace App\Policies;

use App\Models\ProductVideo;
use App\Models\User;

class ProductVideoPolicy
{
    public function update(User $user, ProductVideo $video): bool
    {
        return $video->product && $user->can('update', $video->product);
    }

    public function delete(User $user, ProductVideo $video): bool
    {
        return $this->update($user, $video);
    }
}
