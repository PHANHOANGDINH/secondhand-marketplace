<?php

namespace App\Http\Requests\Domain;

use App\Enums\PaymentMethod;
use App\Enums\ShopOrderStatus;
use Illuminate\Validation\Rule;

class UpdateShopOrderRequest extends DomainRequest
{
    public function rules(): array
    {
        $cod = $this->route('shopOrder')?->order?->payment_method === PaymentMethod::COD;

        return ['expected_status' => ['required', Rule::enum(ShopOrderStatus::class)], 'status' => ['required', Rule::enum(ShopOrderStatus::class)], 'reason' => ['required_if:status,CANCELLED', 'prohibited_unless:status,CANCELLED', 'string', 'max:900'], 'failure_category' => ['required_if:status,CANCELLED', 'prohibited_unless:status,CANCELLED', 'in:OUT_OF_STOCK,DAMAGED,UNFULFILLABLE'], 'cod_collected' => $cod ? ['required_if:status,COMPLETED', 'prohibited_unless:status,COMPLETED', 'boolean', 'accepted_if:status,COMPLETED'] : ['prohibited']];
    }
}
