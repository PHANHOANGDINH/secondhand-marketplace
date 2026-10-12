<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAdmin(User $user): bool
    {
        return (new UserPolicy)->viewAny($user);
    }

    /** An Admin never settles money on an order they placed themselves. */
    public function reconcile(User $user, Payment $payment): bool
    {
        return $this->viewAdmin($user) && $payment->order && $payment->order->buyer_id !== $user->id;
    }
}
