<?php

namespace App\Http\Requests\Domain;

use App\Enums\CategoryStatus;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends DomainRequest
{
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'min:2', 'max:100'], 'description' => ['nullable', 'string', 'max:10000'], 'parent_id' => ['nullable', 'integer', 'min:1', 'exists:categories,id'], 'status' => ['sometimes', Rule::enum(CategoryStatus::class)], 'reason' => ['required', 'string', 'max:1000']];
    }
}
