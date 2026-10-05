<?php

namespace App\Http\Requests\Domain;

use App\Enums\ProductStatus;
use Illuminate\Validation\Rule;

class ModerateProductRequest extends DomainRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('moderate', $this->route('product'));
    }

    public function rules(): array
    {
        return ['expected_status' => ['required', Rule::enum(ProductStatus::class)], 'status' => ['required', Rule::enum(ProductStatus::class)], 'reason' => ['required', 'string', 'max:1000']];
    }
}
