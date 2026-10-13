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
        return ['address_id' => ['required', 'integer', 'min:1'], 'payment_method' => ['required', 'in:COD,ZALOPAY'], 'preferred_payment_method' => ['prohibited_unless:payment_method,ZALOPAY', 'sometimes', 'array', 'max:5'], 'preferred_payment_method.*' => ['required', 'string', 'distinct', Rule::in(ZaloPayGateway::CHANNELS)], 'cart_item_ids' => ['sometimes', 'array', 'min:1', 'max:100'], 'cart_item_ids.*' => ['required', 'integer', 'min:1', 'distinct'], 'platform_voucher' => ['sometimes', 'nullable', 'string', 'max:20'], 'shop_vouchers' => ['sometimes', 'array', 'max:50'], 'shop_vouchers.*' => ['required', 'string', 'max:20']];
    }

    public function after(): array
    {
        return [...parent::after(), function (Validator $validator): void {
            foreach (array_keys((array) $this->input('shop_vouchers', [])) as $shopId) {
                if (! preg_match('/^[1-9][0-9]*$/', (string) $shopId)) {
                    $validator->errors()->add('shop_vouchers', 'Shop vouchers must be keyed by shop id.');
                }
            }
            if (! Str::isUuid($this->header('Idempotency-Key', ''))) {
                $validator->errors()->add('idempotency_key', 'A UUID Idempotency-Key header is required.');
            }
        }];
    }
}
