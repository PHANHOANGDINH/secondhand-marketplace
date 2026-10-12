<?php

namespace App\Http\Requests\Domain;

class ListVideoCommentsRequest extends DomainRequest
{
    public function rules(): array
    {
        return ['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'], 'before_id' => ['sometimes', 'integer', 'min:1']];
    }
}
