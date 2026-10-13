<?php

namespace App\Http\Requests\Domain;

use App\Models\Voucher;
use Illuminate\Validation\Rule;

class VoucherListRequest extends DomainRequest
{
    public function rules(): array
    {
        return [
            'scope' => ['sometimes', Rule::in([Voucher::PLATFORM, Voucher::SHOP])],
            'status' => ['sometimes', Rule::in([Voucher::ACTIVE, Voucher::INACTIVE])],
            'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
