<?php

namespace App\Http\Requests\Domain;

use Illuminate\Validation\Validator;

/** Same body as checkout; only the cart selection and the voucher codes matter for a price preview. */
class CheckoutPreviewRequest extends DomainRequest
{
    public function rules(): array
    {
        return [
            'address_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'payment_method' => ['sometimes', 'in:COD,ZALOPAY'],
            'preferred_payment_method' => ['sometimes', 'array', 'max:5'],
            'preferred_payment_method.*' => ['string'],
            'cart_item_ids' => ['sometimes', 'array', 'min:1', 'max:100'],
            'cart_item_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'platform_voucher' => ['sometimes', 'nullable', 'string', 'max:20'],
            'shop_vouchers' => ['sometimes', 'array', 'max:50'],
            'shop_vouchers.*' => ['required', 'string', 'max:20'],
        ];
    }

    public function after(): array
    {
        return [...parent::after(), function (Validator $validator): void {
            foreach (array_keys((array) $this->input('shop_vouchers', [])) as $shopId) {
                if (! preg_match('/^[1-9][0-9]*$/', (string) $shopId)) {
                    $validator->errors()->add('shop_vouchers', 'Shop vouchers must be keyed by shop id.');
                }
            }
        }];
    }
}
