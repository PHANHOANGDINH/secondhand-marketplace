<?php

namespace App\Http\Requests\Domain;

use App\Enums\AuctionStatus;
use Illuminate\Validation\Rule;

class AuctionListRequest extends DomainRequest
{
    public function rules(): array
    {
        return [...($this->is('api/v1/seller/auctions') ? ['live_now' => ['sometimes', 'boolean'], 'ending_soon' => ['sometimes', 'boolean']] : []), 'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'status' => ['sometimes', Rule::enum(AuctionStatus::class)]];
    }
}
