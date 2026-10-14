<?php

namespace App\Http\Requests\Domain;

use Illuminate\Validation\Rule;

class StoreShopRequest extends DomainRequest
{
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'min:2', 'max:150'], 'description' => ['nullable', 'string', 'max:10000'], 'province_code' => ['sometimes', 'nullable', 'string', Rule::in(array_map('strval', array_keys(config('provinces.list'))))]];
    }
}
