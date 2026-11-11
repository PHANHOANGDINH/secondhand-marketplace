<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/** The user's own picture: the row is swapped in a transaction, the old file goes after the commit. */
class AvatarService
{
    public function __construct(private SquarePicture $pictures) {}

    public function replace(User $actor, UploadedFile $file): User
    {
        $path = $this->pictures->store($file, 'avatars/'.$actor->id);
        try {
            [$user, $previous] = $this->swap($actor, $path);
        } catch (Throwable $exception) {
            $this->pictures->discard($path);
            throw $exception;
        }
        $this->pictures->discard($previous);

        return $user;
    }

    public function remove(User $actor): User
    {
        [$user, $previous] = $this->swap($actor, null);
        $this->pictures->discard($previous);

        return $user;
    }

    /** @return array{0: User, 1: ?string} the saved user and the path it no longer uses */
    private function swap(User $actor, ?string $path): array
    {
        return DB::transaction(function () use ($actor, $path): array {
            $user = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            abort_unless($user->status === UserStatus::ACTIVE, 403);
            $previous = $user->avatar_path;
            $user->forceFill(['avatar_path' => $path])->save();

            return [$user, $previous];
        }, 3);
    }
}
