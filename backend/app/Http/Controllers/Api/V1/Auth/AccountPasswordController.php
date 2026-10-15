<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Resources\AuthUserResource;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountPasswordController extends Controller
{
    public function update(ChangePasswordRequest $request, AuditService $audit): AuthUserResource
    {
        $data = $request->validated();
        $user = DB::transaction(function () use ($request, $data, $audit): User {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            // After a temporary password the owner proved access to the mailbox; otherwise the current password is required.
            if (! $user->must_change_password && ! Hash::check((string) ($data['current_password'] ?? ''), $user->password)) {
                throw ValidationException::withMessages(['current_password' => ['The current password is incorrect.']]);
            }
            if (Hash::check($data['password'], $user->password)) {
                throw ValidationException::withMessages(['password' => ['The new password must be different from the current one.']]);
            }
            $user->forceFill([
                'password' => $data['password'], 'must_change_password' => false,
                'temporary_password' => null, 'temporary_password_expires_at' => null,
            ])->setRememberToken(Str::random(60));
            $user->save();
            $audit->record($user, 'users', $user->id, 'user.password_changed', null, null, null);

            return $user;
        });

        return (new AuthUserResource($user))->additional(['message' => 'Password changed.']);
    }
}
