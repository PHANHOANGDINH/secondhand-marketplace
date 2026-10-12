<?php

namespace App\Http\Requests\Domain;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Validation\Rule;

class AdminUserListRequest extends DomainRequest
{
    public function rules(): array
    {
        return ['search' => ['sometimes', 'nullable', 'string', 'max:100'], 'status' => ['sometimes', Rule::enum(UserStatus::class)], 'role' => ['sometimes', Rule::enum(UserRole::class)], 'flagged' => ['sometimes', 'boolean'], 'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']];
    }
}
