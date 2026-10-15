<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\AuthUserResource;
use App\Models\User;
use App\Services\TemporaryPasswordService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        abort_if(Auth::guard('web')->check(), 409, 'Already authenticated.');
        $user = new User($request->safe()->only(['name', 'email', 'password']));
        $user->role = UserRole::USER;
        $user->status = UserStatus::ACTIVE;
        try {
            $user->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => ['The email has already been taken.']]);
        }
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return (new AuthUserResource($user))->additional(['message' => 'Registration successful.'])->response()->setStatusCode(201);
    }

    public function login(LoginRequest $request, TemporaryPasswordService $temporary): AuthUserResource
    {
        abort_if(Auth::guard('web')->check(), 409, 'Already authenticated.');
        $credentials = $request->safe()->only(['email', 'password']);
        $credentials['status'] = UserStatus::ACTIVE->value;
        if (Auth::guard('web')->attempt($credentials, $request->boolean('remember'))) {
            $temporary->discard(Auth::guard('web')->user());
        } else {
            // A temporary password from "forgot password" signs in once and forces a new password.
            $user = $temporary->consume($credentials['email'], $credentials['password']);
            if (! $user) {
                throw ValidationException::withMessages(['email' => ['The provided credentials are incorrect.']]);
            }
            Auth::guard('web')->login($user, $request->boolean('remember'));
        }
        $request->session()->regenerate();

        return new AuthUserResource(Auth::guard('web')->user());
    }

    public function me(Request $request): JsonResponse
    {
        return (new AuthUserResource($request->user()))->response()->setStatusCode(200);
    }

    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
