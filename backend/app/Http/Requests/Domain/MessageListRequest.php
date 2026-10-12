<?php

namespace App\Http\Requests\Domain;

class MessageListRequest extends DomainRequest
{
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,50'],
            'before_id' => ['sometimes', 'integer', 'min:1', 'prohibits:after_id'],
            'after_id' => ['sometimes', 'integer', 'min:0', 'prohibits:before_id'],
        ];
    }
}
