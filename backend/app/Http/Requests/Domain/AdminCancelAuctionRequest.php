<?php

namespace App\Http\Requests\Domain;

use App\Enums\AuctionStatus;
use Illuminate\Validation\Rule;

class AdminCancelAuctionRequest extends DomainRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cancelAsAdmin', $this->route('auction'));
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('reason'))) {
            $this->merge(['reason' => trim($this->input('reason'))]);
        }
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:1000'], 'expected_status' => ['required', Rule::enum(AuctionStatus::class)]];
    }
}
