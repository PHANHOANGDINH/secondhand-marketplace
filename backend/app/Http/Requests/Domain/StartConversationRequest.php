<?php

namespace App\Http\Requests\Domain;

class StartConversationRequest extends DomainRequest
{
    public function rules(): array
    {
        return [
            'product_id' => ['required_without:seller_id', 'nullable', 'integer', 'min:1'],
            'seller_id' => ['required_without:product_id', 'prohibits:product_id', 'nullable', 'integer', 'min:1'],
        ];
    }
}
