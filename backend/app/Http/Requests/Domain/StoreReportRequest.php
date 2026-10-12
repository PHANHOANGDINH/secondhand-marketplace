<?php

namespace App\Http\Requests\Domain;

use App\Enums\ReportReason;
use App\Enums\ReportTargetType;
use Illuminate\Validation\Rule;

class StoreReportRequest extends DomainRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('description'))) {
            $this->merge(['description' => preg_replace('/^[\s\p{Z}]+|[\s\p{Z}]+$/u', '', $this->input('description'))]);
        }
    }

    public function rules(): array
    {
        return ['target_type' => ['required', Rule::enum(ReportTargetType::class)], 'target_id' => ['required', 'integer', 'min:1'], 'reason' => ['required', Rule::enum(ReportReason::class)], 'description' => ['required_if:reason,OTHER', 'nullable', 'string', 'max:1000']];
    }

    public function messages(): array
    {
        return ['target_type.*' => 'Loại nội dung không hợp lệ.', 'target_id.*' => 'Vui lòng chọn nội dung hợp lệ.', 'reason.*' => 'Vui lòng chọn lý do báo cáo.', 'description.required_if' => 'Vui lòng mô tả lý do khác.', 'description.string' => 'Mô tả phải là văn bản.', 'description.max' => 'Mô tả không được vượt quá 1.000 ký tự.'];
    }
}
