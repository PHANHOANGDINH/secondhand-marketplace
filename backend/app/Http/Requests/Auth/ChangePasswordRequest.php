<?php

namespace App\Http\Requests\Auth;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/** A signed-in user's new password; the current one is asked for unless a temporary password was just used. */
class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'current_password' => [$this->user()?->must_change_password ? 'nullable' : 'required', 'string', 'max:128'],
            'password' => ['required', 'string', 'max:128', 'confirmed', Password::min(12)->letters()->numbers(),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && strlen($value) > 72) {
                        $fail('The password must not exceed 72 bytes.');
                    }
                }],
            'password_confirmation' => ['required', 'string'],
        ];
    }
}
