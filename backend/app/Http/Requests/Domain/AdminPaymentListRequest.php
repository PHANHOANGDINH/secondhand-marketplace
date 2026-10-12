<?php

namespace App\Http\Requests\Domain;

use Illuminate\Validation\Rule;

class AdminPaymentListRequest extends DomainRequest
{
    public function rules(): array
    {
        return ['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'reconciliation' => ['sometimes', Rule::in(['1', 'open', 'resolved', 'all'])]];
    }
}
