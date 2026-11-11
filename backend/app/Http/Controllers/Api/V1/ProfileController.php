<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Domain;
use App\Http\Resources\AuthUserResource;
use App\Models\User;
use App\Services\AvatarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    public function show(Request $request): AuthUserResource
    {
        return new AuthUserResource($request->user());
    }

    public function uploadAvatar(Domain\UploadAvatarRequest $request, AvatarService $avatars): AuthUserResource
    {
        return new AuthUserResource($avatars->replace($request->user(), $request->file('avatar')));
    }

    public function removeAvatar(Request $request, AvatarService $avatars): AuthUserResource
    {
        return new AuthUserResource($avatars->remove($request->user()));
    }

    public function update(Domain\UpdateProfileRequest $request): AuthUserResource
    {
        $user = DB::transaction(function () use ($request): User {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless($user->status === UserStatus::ACTIVE, 403);
            $user->update($request->validated());

            return $user;
        }, 3);

        return new AuthUserResource($user);
    }
}
