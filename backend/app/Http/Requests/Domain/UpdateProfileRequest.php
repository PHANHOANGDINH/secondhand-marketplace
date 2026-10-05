<?php

namespace App\Http\Requests\Domain;

class UpdateProfileRequest extends DomainRequest
{
    public function rules(): array
    {
        return ['name' => ['sometimes', 'required', 'string', 'min:2', 'max:100'], 'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9][0-9 ()-]{6,19}$/']];
    }
}
