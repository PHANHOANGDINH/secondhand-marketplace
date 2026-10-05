<?php

namespace App\Http\Requests\Domain;

class UpdateShopRequest extends DomainRequest
{
    public function rules(): array
    {
        return ['name' => ['sometimes', 'required', 'string', 'min:2', 'max:150'], 'description' => ['sometimes', 'nullable', 'string', 'max:10000']];
    }
}
