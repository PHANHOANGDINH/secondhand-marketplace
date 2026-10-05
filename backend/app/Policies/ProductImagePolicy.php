<?php

namespace App\Policies;

use App\Models\ProductImage;
use App\Models\User;

class ProductImagePolicy
{
    public function update(User $user, ProductImage $image): bool
    {
        return $image->product && $user->can('update', $image->product);
    }

    public function delete(User $user, ProductImage $image): bool
    {
        return $this->update($user, $image);
    }
}
