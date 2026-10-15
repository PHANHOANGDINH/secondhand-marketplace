<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\TemporaryPasswordNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * "Forgot password" by mail: a random one-time password, kept only as a hash next to the normal
 * password. The normal password keeps working, so someone who only knows an email address cannot
 * lock its owner out; using the temporary one signs in once and forces a new password.
 */
class TemporaryPasswordService
{
    private const ALPHABET_LETTERS = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz';

    private const ALPHABET_DIGITS = '23456789';

    public function __construct(private AuditService $audit) {}

    /** Mails a new temporary password to an active account with this email; silent for anyone else. */
    public function issue(string $email): void
    {
        $minutes = max(1, (int) config('auth.temporary_password_minutes'));
        $issued = DB::transaction(function () use ($email, $minutes): ?array {
            $user = User::where('email', $email)->where('status', UserStatus::ACTIVE)->lockForUpdate()->first();
            if (! $user) {
                return null;
            }
            $password = $this->generate();
            $user->forceFill(['temporary_password' => Hash::make($password), 'temporary_password_expires_at' => now()->addMinutes($minutes)])->save();

            return [$user, $password];
        });
        if (! $issued) {
            return;
        }
        [$user, $password] = $issued;
        try {
            $user->notify(new TemporaryPasswordNotification($password, $minutes));
        } catch (Throwable $exception) {
            // Never log mail content, credentials, email addresses or the password.
            Log::warning('Temporary password delivery failed.', ['exception_type' => $exception::class]);
        }
    }

    /**
     * Signs in with an unexpired temporary password: it is used up at once and the account must set a
     * new password. Returns the user, or null when the password is not a valid temporary one.
     */
    public function consume(string $email, string $password): ?User
    {
        return DB::transaction(function () use ($email, $password): ?User {
            $user = User::where('email', $email)->where('status', UserStatus::ACTIVE)->lockForUpdate()->first();
            if (! $user || ! $user->temporary_password || ! $user->temporary_password_expires_at?->isFuture() || ! Hash::check($password, $user->temporary_password)) {
                return null;
            }
            $user->forceFill(['temporary_password' => null, 'temporary_password_expires_at' => null, 'must_change_password' => true])->save();
            $this->audit->record($user, 'users', $user->id, 'user.temporary_password_used', null, null, null);

            return $user;
        });
    }

    /** A normal sign-in shows the owner remembered the password: a pending temporary one is dropped. */
    public function discard(User $user): void
    {
        if ($user->temporary_password !== null) {
            $user->forceFill(['temporary_password' => null, 'temporary_password_expires_at' => null])->save();
        }
    }

    /** 12 characters without look-alikes (0/O, 1/l/I), always with letters and digits. */
    private function generate(): string
    {
        $all = self::ALPHABET_LETTERS.self::ALPHABET_DIGITS;
        $characters = [
            self::ALPHABET_LETTERS[random_int(0, strlen(self::ALPHABET_LETTERS) - 1)],
            self::ALPHABET_DIGITS[random_int(0, strlen(self::ALPHABET_DIGITS) - 1)],
        ];
        while (count($characters) < 12) {
            $characters[] = $all[random_int(0, strlen($all) - 1)];
        }
        for ($i = count($characters) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$characters[$i], $characters[$j]] = [$characters[$j], $characters[$i]];
        }

        return implode('', $characters);
    }
}
