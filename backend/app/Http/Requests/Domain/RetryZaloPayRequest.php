<?php

namespace App\Http\Requests\Domain;

use App\Services\ZaloPayGateway;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RetryZaloPayRequest extends DomainRequest
{
    public function rules(): array
    {
        return ['preferred_payment_method' => ['sometimes', 'array', 'max:5'], 'preferred_payment_method.*' => ['required', 'string', 'distinct', Rule::in(ZaloPayGateway::CHANNELS)]];
    }

    public function after(): array
    {
        return [...parent::after(), function (Validator $validator): void {
            if (! Str::isUuid($this->header('Idempotency-Key', ''))) {
                $validator->errors()->add('idempotency_key', 'A UUID Idempotency-Key header is required.');
            }
        }];
    }
}
