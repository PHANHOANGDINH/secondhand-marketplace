<?php

namespace App\Http\Requests\Domain;

class CategoryListRequest extends DomainRequest
{
    public function rules(): array
    {
        return ['all' => ['sometimes', 'boolean'], 'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'parent_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'exists:categories,id']];
    }
}
