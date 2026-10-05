<?php

namespace App\Http\Requests\Domain;

class UpdateReviewRequest extends DomainRequest
{
    public function rules(): array
    {
        return ['rating' => ['sometimes', 'required', 'integer', 'between:1,5'], 'comment' => ['sometimes', 'nullable', 'string', 'max:2000']];
    }
}
