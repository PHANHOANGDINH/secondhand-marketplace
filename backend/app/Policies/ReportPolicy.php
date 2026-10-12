<?php

namespace App\Policies;

use App\Enums\UserStatus;
use App\Models\Report;
use App\Models\User;
use App\Services\ReportService;

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

    /** An Admin cannot rule on a report against their own shop, product or comment. */
    public function moderate(User $user, Report $report): bool
    {
        return $this->viewAdmin($user) && (app(ReportService::class)->targetOwners(collect([$report]))[$report->id] ?? null) !== $user->id;
    }
}
