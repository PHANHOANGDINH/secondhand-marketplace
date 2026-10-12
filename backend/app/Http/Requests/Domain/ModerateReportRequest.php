<?php

namespace App\Http\Requests\Domain;

use App\Enums\ReportStatus;
use Illuminate\Validation\Rule;

class ModerateReportRequest extends DomainRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('admin_note'))) {
            $this->merge(['admin_note' => trim($this->input('admin_note'))]);
        }
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(ReportStatus::class)], 'expected_status' => ['required', Rule::enum(ReportStatus::class)], 'admin_note' => ['nullable', 'string', 'max:1000']];
    }

    public function messages(): array
    {
        return ['admin_note.max' => 'Ghi chú không được vượt quá 1.000 ký tự.'];
    }
}
