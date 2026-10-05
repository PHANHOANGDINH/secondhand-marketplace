<?php

namespace App\Http\Requests\Domain;

use App\Enums\UserStatus;
use Illuminate\Validation\Rule;

class ModerateUserRequest extends DomainRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('moderate', $this->route('user'));
    }

    public function rules(): array
    {
        return ['expected_status' => ['required', Rule::enum(UserStatus::class)], 'status' => ['required', Rule::enum(UserStatus::class)], 'reason' => ['required', 'string', 'max:1000']];
    }
}
