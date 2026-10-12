<?php

namespace App\Policies;

use App\Enums\UserStatus;
use App\Models\Report;
use App\Models\User;

class ReportPolicy
{
    public function create(User $user): bool
    {
        return ! $user->trashed() && $user->status === UserStatus::ACTIVE;
    }

    public function view(User $user, Report $report): bool
    {
        return $this->create($user) && $report->reporter_user_id === $user->id;
    }

    public function viewAdmin(User $user): bool
    {
        return (new UserPolicy)->viewAny($user);
    }

    public function moderate(User $user, Report $report): bool
    {
        return $this->viewAdmin($user);
    }
}
