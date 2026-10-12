<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
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
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        try {
            Password::sendResetLink($request->safe()->only('email'));
        } catch (Throwable $exception) {
            // Never log mail content, credentials, email addresses or reset tokens.
            Log::warning('Password reset delivery failed.', ['exception_type' => $exception::class]);
        }

        return response()->json(['message' => 'If an account matches that email, password reset instructions have been sent.']);
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
