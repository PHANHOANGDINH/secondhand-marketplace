<?php

namespace App\Http\Requests\Domain;

use App\Models\Auction;
use App\Services\DecimalPrice;

class StoreAuctionRequest extends DomainRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Auction::class);
    }

    public function rules(): array
    {
        return ['product_id' => ['required', 'integer', 'min:1', 'exists:products,id'], 'start_price' => ['required', new DecimalPrice], 'bid_increment' => ['required', new DecimalPrice], 'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at']];
    }
}
