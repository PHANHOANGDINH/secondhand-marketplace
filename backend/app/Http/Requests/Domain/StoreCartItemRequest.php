<?php

namespace App\Http\Requests\Domain;

class StoreCartItemRequest extends DomainRequest
{
    public function rules(): array
    {
        return ['product_id' => ['required', 'integer', 'min:1', 'exists:products,id'], 'quantity' => ['required', 'integer', 'min:1', 'max:99']];
    }
}
