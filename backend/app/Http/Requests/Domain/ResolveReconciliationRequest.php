<?php

namespace App\Http\Requests\Domain;

use App\Enums\PaymentStatus;
use App\Services\PaymentReconciliationService;
use Illuminate\Validation\Rule;

class ResolveReconciliationRequest extends DomainRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reconcile', $this->route('payment'));
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('note'))) {
            $this->merge(['note' => trim($this->input('note'))]);
        }
    }

    public function rules(): array
    {
        return ['outcome' => ['required', Rule::in(PaymentReconciliationService::OUTCOMES)], 'note' => ['required', 'string', 'max:1000'], 'expected_status' => ['required', Rule::enum(PaymentStatus::class)]];
    }
}
