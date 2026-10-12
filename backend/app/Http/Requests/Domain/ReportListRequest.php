<?php

namespace App\Http\Requests\Domain;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Enums\ReportTargetType;
use Illuminate\Validation\Rule;

class ReportListRequest extends DomainRequest
{
    public function rules(): array
    {
        return ['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'between:1,100'], 'status' => ['sometimes', Rule::enum(ReportStatus::class)], 'target_type' => ['sometimes', Rule::enum(ReportTargetType::class)], 'reason' => ['sometimes', Rule::enum(ReportReason::class)], 'sort' => ['sometimes', Rule::in(['newest', 'oldest'])], 'from' => ['sometimes', 'date_format:Y-m-d'], 'to' => ['sometimes', 'date_format:Y-m-d', ...($this->filled('from') ? ['after_or_equal:from'] : [])]];
    }
}
