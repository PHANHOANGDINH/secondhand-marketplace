<?php

namespace App\Http\Requests\Domain;

use App\Enums\OrderStatus;
use Illuminate\Validation\Rule;

class OrderListRequest extends DomainRequest
{
    public function rules(): array
    {
        return ['overall_status' => ['sometimes', Rule::enum(OrderStatus::class)], 'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']];
    }
}
