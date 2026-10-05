<?php

namespace App\Http\Requests\Domain;

class StoreReviewRequest extends DomainRequest
{
    public function rules(): array
    {
        return ['rating' => ['required', 'integer', 'between:1,5'], 'comment' => ['sometimes', 'nullable', 'string', 'max:2000']];
    }
}
