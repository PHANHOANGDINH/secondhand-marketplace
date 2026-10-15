<?php

namespace App\Http\Requests\Domain;

use App\Enums\ProductCondition;
use App\Models\Product;
use App\Services\DecimalPrice;
use Illuminate\Validation\Rule;

class StoreProductRequest extends DomainRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Product::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'], 'description' => ['required', 'string', 'max:10000'],
            'category_id' => ['required', 'integer', 'min:1', 'exists:categories,id'], 'price' => ['required', new DecimalPrice(minimum: '1')], 'compare_at_price' => ['nullable', new DecimalPrice(minimum: '1')],
            'stock_quantity' => ['required', 'integer', 'min:0', 'max:4294967295'], 'condition' => ['required', Rule::enum(ProductCondition::class)],
        ];
    }
}
