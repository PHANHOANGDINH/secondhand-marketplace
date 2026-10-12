<?php

namespace App\Http\Requests\Domain;

use App\Enums\AuctionStatus;
use Illuminate\Validation\Rule;

class AdminAuctionListRequest extends DomainRequest
{
    public function rules(): array
    {
        return ['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'status' => ['sometimes', Rule::enum(AuctionStatus::class)], 'settlement' => ['sometimes', Rule::in(['NO_WINNER', 'AWAITING_CHECKOUT', 'ORDER_CREATED', 'CHECKOUT_EXPIRED'])], 'overdue' => ['sometimes', 'boolean'], 'auction' => ['sometimes', 'integer', 'min:1']];
    }
}
