<?php

namespace App\Http\Requests\Domain;

use App\Enums\AuctionStatus;
use Illuminate\Validation\Rule;

/** "Bán ngay": the seller confirms the status and the highest bid they saw; a note is optional. */
class AcceptAuctionBidRequest extends DomainRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('acceptBid', $this->route('auction'));
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('reason'))) {
            $this->merge(['reason' => trim($this->input('reason'))]);
        }
    }

    public function rules(): array
    {
        return [
            'expected_status' => ['required', Rule::enum(AuctionStatus::class)],
            'expected_bid_id' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
