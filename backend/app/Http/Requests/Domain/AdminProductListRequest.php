<?php

namespace App\Http\Requests\Domain;

class AdminProductListRequest extends SellerProductListRequest
{
    public function rules(): array
    {
        return [...parent::rules(), 'shop' => ['sometimes', 'integer', 'min:1', 'exists:shops,id'], 'category' => ['sometimes', 'integer', 'min:1', 'exists:categories,id']];
    }
}
