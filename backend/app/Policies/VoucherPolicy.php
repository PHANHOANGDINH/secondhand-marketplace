<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Voucher;

class VoucherPolicy
{
    private function admin(User $user): bool
    {
        return (new UserPolicy)->viewAny($user);
    }

    /** A seller sees and manages only the vouchers of their own shop; an admin manages platform vouchers. */
    private function owns(User $user, Voucher $voucher): bool
    {
        return $voucher->scope === Voucher::SHOP
            ? $voucher->shop !== null && ! $voucher->shop->trashed() && (int) $voucher->shop->owner_id === (int) $user->id
            : $this->admin($user);
    }

    public function viewAdmin(User $user): bool
    {
        return $this->admin($user);
    }

    public function createPlatform(User $user): bool
    {
        return $this->admin($user);
    }

    public function view(User $user, Voucher $voucher): bool
    {
        return $this->owns($user, $voucher);
    }

    public function update(User $user, Voucher $voucher): bool
    {
        return $this->owns($user, $voucher);
    }

    public function delete(User $user, Voucher $voucher): bool
    {
        return $this->owns($user, $voucher);
    }
}
