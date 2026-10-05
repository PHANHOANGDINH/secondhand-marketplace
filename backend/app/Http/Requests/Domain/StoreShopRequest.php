<?php

namespace App\Http\Requests\Domain;

class StoreShopRequest extends DomainRequest
{
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'min:2', 'max:150'], 'description' => ['nullable', 'string', 'max:10000']];
    }
}
