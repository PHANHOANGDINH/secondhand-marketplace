<?php

namespace App\Http\Requests\Domain;

use App\Services\ZaloPayGateway;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CheckoutRequest extends DomainRequest
{
    public function rules(): array
    {
        return ['address_id' => ['required', 'integer', 'min:1'], 'payment_method' => ['required', 'in:COD,ZALOPAY'], 'preferred_payment_method' => ['prohibited_unless:payment_method,ZALOPAY', 'sometimes', 'array', 'max:5'], 'preferred_payment_method.*' => ['required', 'string', 'distinct', Rule::in(ZaloPayGateway::CHANNELS)]];
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
