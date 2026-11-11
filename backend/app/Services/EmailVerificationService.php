<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Email verification, two ways from one mail: a signed link, or a six-digit code typed on the page.
 * The link opens a frontend page which posts its signature to the API, so scanners that open links in
 * a mail cannot verify an address. The code is kept only as a hash, expires, and dies after a few wrong tries.
 */
class EmailVerificationService
{
    public const MAX_CODE_ATTEMPTS = 5;

    public function __construct(private AuditService $audit) {}

    /** Mails a new code and link; a failure never undoes the registration, the user can ask for it again. */
    public function send(User $user): void
    {
        try {
            $code = $this->issueCode($user);
            $user->notify(new VerifyEmailNotification($this->link($user), $this->linkMinutes(), $code, $this->codeMinutes()));
        } catch (Throwable $exception) {
            // Never log mail content, credentials, codes or email addresses.
            Log::warning('Email verification delivery failed.', ['exception_type' => $exception::class]);
        }
    }

    /** Mails a new code and link to an active account still waiting for verification; silent for anyone else. */
    public function resend(string $email): void
    {
        $user = User::where('email', $email)->where('status', UserStatus::ACTIVE)->first();
        if ($user?->requiresEmailVerification()) {
            $this->send($user);
        }
    }

    /** Returns false when the account or address does not match the link; opening a used link is fine. */
    public function verifyLink(int $id, string $hash): bool
    {
        return DB::transaction(function () use ($id, $hash): bool {
            $user = User::whereKey($id)->lockForUpdate()->first();
            if (! $user || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
                return false;
            }
            if (! $user->hasVerifiedEmail()) {
                $this->confirm($user, 'link');
            }

            return true;
        });
    }

    /** One answer for a wrong, expired or used-up code and for an account that is not waiting: false. */
    public function verifyCode(string $email, string $code): bool
    {
        return DB::transaction(function () use ($email, $code): bool {
            $user = User::where('email', $email)->where('status', UserStatus::ACTIVE)->lockForUpdate()->first();
            if (! $user?->requiresEmailVerification() || ! $user->email_verification_code
                || ! $user->email_verification_code_expires_at?->isFuture()
                || $user->email_verification_code_attempts >= self::MAX_CODE_ATTEMPTS) {
                return false;
            }
            if (! Hash::check($code, $user->email_verification_code)) {
                $user->forceFill(['email_verification_code_attempts' => $user->email_verification_code_attempts + 1])->save();

                return false;
            }
            $this->confirm($user, 'code');

            return true;
        });
    }

    private function confirm(User $user, string $how): void
    {
        $user->forceFill([
            'email_verified_at' => now(), 'email_verification_code' => null,
            'email_verification_code_expires_at' => null, 'email_verification_code_attempts' => 0,
        ])->save();
        $this->audit->record($user, 'users', $user->id, 'user.email_verified', null, null, $how);
    }

    /** A new code replaces the previous one and gets its own tries. */
    private function issueCode(User $user): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        DB::transaction(function () use ($user, $code): void {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail()->forceFill([
                'email_verification_code' => Hash::make($code),
                'email_verification_code_expires_at' => now()->addMinutes($this->codeMinutes()),
                'email_verification_code_attempts' => 0,
            ])->save();
        });

        return $code;
    }

    private function link(User $user): string
    {
        $signed = URL::temporarySignedRoute('auth.email-verification.verify', now()->addMinutes($this->linkMinutes()), [
            'id' => $user->id, 'hash' => sha1($user->getEmailForVerification()),
        ], false);
        parse_str((string) parse_url($signed, PHP_URL_QUERY), $query);

        return config('auth.email_verification.url').'?'.http_build_query([
            'id' => $user->id, 'hash' => sha1($user->getEmailForVerification()), ...$query,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    private function linkMinutes(): int
    {
        return max(1, (int) config('auth.email_verification.minutes'));
    }

    private function codeMinutes(): int
    {
        return max(1, (int) config('auth.email_verification.code_minutes'));
    }
}
