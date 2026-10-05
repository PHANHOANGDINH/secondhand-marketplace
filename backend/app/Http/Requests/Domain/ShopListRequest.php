<?php

namespace App\Http\Requests\Domain;

use App\Enums\ShopStatus;
use Illuminate\Validation\Rule;

class ShopListRequest extends DomainRequest
{
    public function rules(): array
    {
        return ['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'search' => ['sometimes', 'nullable', 'string', 'max:100'], 'status' => [$this->is('api/v1/admin/*') ? 'sometimes' : 'prohibited', Rule::enum(ShopStatus::class)]];
    }
}
