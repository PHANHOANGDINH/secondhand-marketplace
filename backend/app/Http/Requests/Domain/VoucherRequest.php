<?php

namespace App\Http\Requests\Domain;

use App\Models\Voucher;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Create (POST) and update (PATCH) of a voucher; scope and shop come from the route, never from the client. */
class VoucherRequest extends DomainRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
    }

    public function rules(): array
    {
        $create = $this->isMethod('POST');
        $need = $create ? 'required' : 'sometimes';
        $money = 'regex:/^[0-9]{1,13}(\.[0-9]{1,2})?$/';

        return [
            'code' => [$need, 'string', 'regex:/^[A-Z0-9]{4,20}$/', ...($create ? [Rule::unique('vouchers', 'code')] : [])],
            'type' => [$need, Rule::in([Voucher::PERCENT, Voucher::FIXED])],
            'value' => [$need, 'string', $money],
            'min_order' => ['sometimes', 'string', $money],
            'max_discount' => ['sometimes', 'nullable', 'string', $money],
            'usage_limit' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4000000000'],
            'per_user_limit' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4000000000'],
            'starts_at' => [$need, 'date'],
            'ends_at' => [$need, 'date', ...($this->filled('starts_at') ? ['after:starts_at'] : [])],
            'status' => ['sometimes', Rule::in([Voucher::ACTIVE, Voucher::INACTIVE])],
            'expected_status' => [...($create ? ['prohibited'] : ['required_with:status']), Rule::in([Voucher::ACTIVE, Voucher::INACTIVE])],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'Mã gồm 4–20 ký tự, chỉ chữ in hoa A–Z và số.',
            'code.unique' => 'Mã này đã tồn tại.',
            'ends_at.after' => 'Ngày kết thúc phải sau ngày bắt đầu.',
        ];
    }

    public function after(): array
    {
        return [...parent::after(), function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $type = $this->input('type');
            $value = $this->input('value');
            if ($value !== null && bccomp($value, '0', 2) <= 0) {
                $validator->errors()->add('value', 'Giá trị giảm phải lớn hơn 0.');
            }
            if ($type === Voucher::PERCENT && $value !== null && (bccomp($value, '1', 2) < 0 || bccomp($value, '100', 2) > 0)) {
                $validator->errors()->add('value', 'Phần trăm giảm phải từ 1 đến 100.');
            }
            if ($type === Voucher::FIXED && $this->filled('max_discount')) {
                $validator->errors()->add('max_discount', 'Mức giảm tối đa chỉ dùng cho mã giảm theo phần trăm.');
            }
            if ($this->filled('max_discount') && bccomp($this->input('max_discount'), '0', 2) <= 0) {
                $validator->errors()->add('max_discount', 'Mức giảm tối đa phải lớn hơn 0.');
            }
        }];
    }
}
