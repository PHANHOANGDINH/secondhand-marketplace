<?php

namespace App\Http\Requests\Domain;

use Illuminate\Validation\Rule;

class UpdateAddressRequest extends DomainRequest
{
    public function rules(): array
    {
        return ['recipient_name' => ['sometimes', 'required', 'string', 'max:100'], 'phone' => ['sometimes', 'required', 'string', 'max:20', 'regex:/^\+?[0-9][0-9 ()-]{6,19}$/'], 'address_line' => ['sometimes', 'required', 'string', 'max:255'], 'ward' => ['sometimes', 'required', 'string', 'max:100'], 'district' => ['sometimes', 'nullable', 'string', 'max:100'], 'province' => ['sometimes', 'required', 'string', 'max:100'], 'country_code' => ['sometimes', Rule::in(['VN'])], 'is_default' => ['sometimes', 'boolean']];
    }

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('address'));
    }
}
