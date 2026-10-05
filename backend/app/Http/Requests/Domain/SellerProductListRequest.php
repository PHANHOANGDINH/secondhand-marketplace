<?php

namespace App\Http\Requests\Domain;

use App\Enums\ProductStatus;
use Illuminate\Validation\Rule;

class SellerProductListRequest extends DomainRequest
{
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:100', Rule::prohibitedIf(fn (): bool => $this->filled('keyword'))], 'keyword' => ['sometimes', 'nullable', 'string', 'max:100', Rule::prohibitedIf(fn (): bool => $this->filled('search'))], 'status' => ['sometimes', Rule::enum(ProductStatus::class)],
            'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
