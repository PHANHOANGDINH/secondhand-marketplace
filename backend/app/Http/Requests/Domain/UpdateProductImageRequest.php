<?php

namespace App\Http\Requests\Domain;

use Illuminate\Validation\Rule;

class UpdateProductImageRequest extends DomainRequest
{
    public function rules(): array
    {
        return ['is_primary' => ['required_without:sort_order', Rule::prohibitedIf(fn (): bool => $this->filled('sort_order')), Rule::in([true, 1, '1'])], 'sort_order' => ['required_without:is_primary', Rule::prohibitedIf(fn (): bool => $this->filled('is_primary')), 'integer', 'min:0', 'max:7']];
    }
}
