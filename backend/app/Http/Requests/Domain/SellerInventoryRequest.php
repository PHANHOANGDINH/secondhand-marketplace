<?php

namespace App\Http\Requests\Domain;

use Illuminate\Validation\Rule;

class SellerInventoryRequest extends DomainRequest
{
    public function rules(): array
    {
        return ['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'filter' => ['sometimes', Rule::in(['all', 'low_stock', 'out_of_stock', 'active', 'pending', 'hidden', 'rejected'])],
            'sort' => ['sometimes', Rule::in(['stock_asc', 'stock_desc', 'newest', 'updated'])], 'search' => ['sometimes', 'string', 'max:200']];
    }
}
