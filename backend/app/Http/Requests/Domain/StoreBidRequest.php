<?php

namespace App\Http\Requests\Domain;

use App\Services\DecimalPrice;

class StoreBidRequest extends DomainRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('bid', $this->route('auction'));
    }

    public function rules(): array
    {
        return ['amount' => ['required', new DecimalPrice]];
    }
}
