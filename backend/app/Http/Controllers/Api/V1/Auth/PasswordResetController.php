<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Services\TemporaryPasswordService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class PasswordResetController extends Controller
{
    /** Mails a one-time temporary password; the answer is the same whether or not the account exists. */
    public function forgot(ForgotPasswordRequest $request, TemporaryPasswordService $temporary): JsonResponse
    {
        try {
            $temporary->issue($request->safe()->string('email')->toString());
        } catch (Throwable $exception) {
            // Never log mail content, credentials, email addresses or the password.
            Log::warning('Temporary password issue failed.', ['exception_type' => $exception::class]);
        }

        return response()->json(['message' => 'If an account matches that email, a temporary password has been sent.']);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $credentials = $request->safe()->only(['email', 'token', 'password', 'password_confirmation']);
        $status = DB::transaction(function () use ($credentials): string {
            // Serialize consumption so simultaneous requests cannot reuse a valid token.
            $broker = config('auth.defaults.passwords');
            DB::table(config("auth.passwords.{$broker}.table"))
                ->where('email', $credentials['email'])->lockForUpdate()->first();

            return Password::reset($credentials, function (User $user, string $password): void {
                $user->forceFill(['password' => Hash::make($password)])->setRememberToken(Str::random(60));
                $user->save();
                event(new PasswordReset($user));
            });
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['token' => ['This password reset link is invalid or expired. Please request a new link.']]);
        }

        return response()->json(['message' => 'Password has been reset. Please sign in.']);
    }
}
