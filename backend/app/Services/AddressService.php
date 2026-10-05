<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\Address;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AddressService
{
    private function lockOwner(User $user): User
    {
        $owner = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
        abort_unless($owner->status === UserStatus::ACTIVE, 403);

        return $owner;
    }

    private function normalizeDefault(User $user): void
    {
        $default = $user->addresses()->where('is_default', true)->orderBy('id')->first() ?? $user->addresses()->orderBy('id')->first();
        if ($default) {
            $user->addresses()->whereKeyNot($default->id)->update(['is_default' => false]);
            $default->update(['is_default' => true]);
        }
    }

    public function create(User $user, array $data): Address
    {
        return DB::transaction(function () use ($user, $data): Address {
            $owner = $this->lockOwner($user);
            if ($data['is_default'] ?? false) {
                $owner->addresses()->update(['is_default' => false]);
            }
            $address = $owner->addresses()->create($data);
            $this->normalizeDefault($owner);

            return $address->refresh();
        }, 3);
    }

    public function update(User $user, Address $address, array $data): Address
    {
        return DB::transaction(function () use ($user, $address, $data): Address {
            $owner = $this->lockOwner($user);
            $locked = Address::whereKey($address->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($owner)->authorize('update', $locked);
            if ($data['is_default'] ?? false) {
                $owner->addresses()->whereKeyNot($locked->id)->update(['is_default' => false]);
            }
            $locked->update($data);
            $this->normalizeDefault($owner);

            return $locked->refresh();
        }, 3);
    }

    public function setDefault(User $user, Address $address): Address
    {
        return $this->update($user, $address, ['is_default' => true]);
    }

    public function delete(User $user, Address $address): void
    {
        DB::transaction(function () use ($user, $address): void {
            $owner = $this->lockOwner($user);
            $locked = Address::whereKey($address->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($owner)->authorize('delete', $locked);
            $locked->delete();
            $this->normalizeDefault($owner);
        }, 3);
    }
}
