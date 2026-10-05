<?php

namespace App\Http\Requests\Domain;

use App\Services\SellerReportRange;
use Illuminate\Validation\Validator;

class SellerDashboardRequest extends DomainRequest
{
    public function rules(): array
    {
        return ['from' => ['required_with:to', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:9998-12-31'],
            'to' => ['required_with:from', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:9998-12-31', ...($this->filled('from') ? ['after_or_equal:from'] : [])]];
    }

    public function after(): array
    {
        return [...parent::after(), function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $range = SellerReportRange::fromFilters($this->only(['from', 'to']));
            if ($range->from->diffInDays($range->to) > 365) {
                $validator->errors()->add('to', 'Choose a range of at most 366 calendar days, including both endpoints.');
            }
        }];
    }
}
