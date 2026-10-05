<?php

namespace App\Http\Requests\Domain;

use App\Enums\ShopOrderStatus;
use Illuminate\Validation\Rule;

class CancelShopOrderRequest extends DomainRequest
{
    public function rules(): array
    {
        return ['expected_status' => ['required', Rule::enum(ShopOrderStatus::class)], 'reason' => ['required', 'string', 'max:900']];
    }
}
