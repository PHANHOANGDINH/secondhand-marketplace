<?php

namespace App\Http\Requests\Domain;

class SellerRevenueRequest extends SellerDashboardRequest
{
    public function rules(): array
    {
        return [...parent::rules(), 'group_by' => ['sometimes', 'in:day,month']];
    }
}
