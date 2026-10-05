<?php

namespace App\Http\Requests\Domain;

use App\Enums\ProductCondition;
use App\Services\CategoryService;
use App\Services\DecimalPrice;
use App\Services\ShopService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PublicProductListRequest extends DomainRequest
{
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:100', Rule::prohibitedIf(fn (): bool => $this->filled('keyword'))], 'keyword' => ['sometimes', 'nullable', 'string', 'max:100', Rule::prohibitedIf(fn (): bool => $this->filled('search'))], 'category' => ['sometimes', 'integer', 'min:1'],
            'shop' => ['sometimes', 'integer', 'min:1'], 'condition' => ['sometimes', Rule::enum(ProductCondition::class)],
            'min_price' => ['sometimes', new DecimalPrice(true)], 'max_price' => ['sometimes', new DecimalPrice(true)],
            'sort' => ['sometimes', Rule::in(['newest', 'price_asc', 'price_desc'])],
            'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function after(): array
    {
        return [...parent::after(), function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            if ($this->exists('category') && ! app(CategoryService::class)->publicQuery()->whereKey($this->input('category'))->exists()) {
                $validator->errors()->add('category', 'Choose a public category.');
            }
            if ($this->exists('shop') && ! app(ShopService::class)->publicQuery()->whereKey($this->input('shop'))->exists()) {
                $validator->errors()->add('shop', 'Choose a public shop.');
            }
            if ($this->exists('min_price') && $this->exists('max_price') && bccomp($this->input('min_price'), $this->input('max_price'), 2) > 0) {
                $validator->errors()->add('max_price', 'Maximum price must be greater than or equal to minimum price.');
            }
        }];
    }
}
