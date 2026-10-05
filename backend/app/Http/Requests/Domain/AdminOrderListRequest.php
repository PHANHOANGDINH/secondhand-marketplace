<?php

namespace App\Http\Requests\Domain;

use App\Enums\OrderStatus;
use App\Enums\ShopOrderStatus;
use Illuminate\Validation\Rule;

class AdminOrderListRequest extends SellerDashboardRequest
{
    public function rules(): array
    {
        return [...parent::rules(), 'overall_status' => ['sometimes', Rule::enum(OrderStatus::class)], 'shop_order_status' => ['sometimes', Rule::enum(ShopOrderStatus::class)], 'buyer' => ['sometimes', 'integer', 'min:1'], 'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']];
    }
}
