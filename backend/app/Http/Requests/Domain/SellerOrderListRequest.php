<?php

namespace App\Http\Requests\Domain;

use App\Enums\ShopOrderStatus;
use Illuminate\Validation\Rule;

class SellerOrderListRequest extends OrderListRequest
{
    public function rules(): array
    {
        return [...array_diff_key(parent::rules(), ['overall_status' => true]), 'action_required' => ['sometimes', 'boolean'], 'status' => ['sometimes', Rule::enum(ShopOrderStatus::class)], 'from' => ['sometimes', 'date_format:Y-m-d'], 'to' => ['sometimes', 'date_format:Y-m-d', ...($this->filled('from') ? ['after_or_equal:from'] : [])]];
    }
}
