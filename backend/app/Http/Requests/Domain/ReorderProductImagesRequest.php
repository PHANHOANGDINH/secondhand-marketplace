<?php

namespace App\Http\Requests\Domain;

class ReorderProductImagesRequest extends DomainRequest
{
    public function rules(): array
    {
        return ['image_ids' => ['required', 'array', 'min:1', 'max:8'], 'image_ids.*' => ['required', 'integer', 'min:1', 'distinct']];
    }
}
