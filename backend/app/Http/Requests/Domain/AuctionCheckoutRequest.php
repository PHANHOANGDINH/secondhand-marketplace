<?php

namespace App\Http\Requests\Domain;

/** Same body as a fixed-price checkout, minus what only makes sense for a cart: vouchers and a cart selection. */
class AuctionCheckoutRequest extends CheckoutRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'cart_item_ids' => ['prohibited'], 'cart_item_ids.*' => ['prohibited'],
            'platform_voucher' => ['prohibited'], 'shop_vouchers' => ['prohibited'], 'shop_vouchers.*' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'platform_voucher.prohibited' => 'Đơn đấu giá không áp dụng mã giảm giá.',
            'shop_vouchers.prohibited' => 'Đơn đấu giá không áp dụng mã giảm giá.',
        ];
    }
}
