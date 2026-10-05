<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AdminUserService
{
    public function __construct(private AuditService $audit, private OrderLocks $locks) {}

    public function query(array $filters): Builder
    {
        $query = User::withTrashed();
        foreach (['status', 'role'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (($search = $filters['search'] ?? '') !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
            $query->where(fn (Builder $q) => $q->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("email LIKE ? ESCAPE '!'", [$pattern]));
        }

        return $query->orderByDesc('id');
    }

    public function moderate(User $actor, User $target, array $data): User
    {
        return DB::transaction(function () use ($actor, $target, $data): User {
            $accounts = $this->locks->accounts([$actor->id, $target->id]);
            $admin = $accounts[$actor->id];
            $locked = $accounts[$target->id];
            Gate::forUser($admin)->authorize('moderate', $locked);
            $from = $locked->status->value;
            abort_unless($data['expected_status'] === $from && $data['status'] !== $from, 409, 'User status has changed or transition is invalid.');
            if ($locked->role === UserRole::ADMIN && $data['status'] === UserStatus::SUSPENDED->value) {
                // Actor and target locks also serialize cross-suspension; the actor must remain active.
                abort_unless(User::where('role', UserRole::ADMIN)->where('status', UserStatus::ACTIVE)->count() > 1, 409, 'The last active administrator cannot be suspended.');
            }
            $locked->forceFill(['status' => $data['status']])->save();
            $this->audit->record($admin, 'users', $locked->id, 'user.status_changed', $from, $data['status'], $data['reason']);

            return $locked;
        }, 3);
    }
}
