<?php

namespace App\Policies;

use App\Enums\ShopStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    private function active(User $user): bool
    {
        return $user->status === UserStatus::ACTIVE && ! $user->trashed();
    }

    public function viewAny(User $user): bool
    {
        return $this->active($user) && $user->shop()->exists();
    }

    public function create(User $user): bool
    {
        return $this->active($user) && $user->shop()->whereIn('status', [ShopStatus::PENDING, ShopStatus::ACTIVE])->exists();
    }

    public function view(User $user, Product $product): bool
    {
        return $this->active($user) && ! $product->trashed() && $product->shop && ! $product->shop->trashed() && $product->shop->owner_id === $user->id;
    }

    public function update(User $user, Product $product): bool
    {
        return $this->view($user, $product) && in_array($product->shop->status, [ShopStatus::PENDING, ShopStatus::ACTIVE], true);
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->update($user, $product);
    }

    public function submit(User $user, Product $product): bool
    {
        return $this->update($user, $product);
    }

    public function hide(User $user, Product $product): bool
    {
        return $this->update($user, $product);
    }

    public function viewAdmin(User $user): bool
    {
        return $this->active($user) && $user->role === UserRole::ADMIN;
    }

    public function moderate(User $user, Product $product): bool
    {
        return $this->viewAdmin($user) && ! $product->trashed() && $product->shop && ! $product->shop->trashed() && $product->shop->owner_id !== $user->id;
    }
}
