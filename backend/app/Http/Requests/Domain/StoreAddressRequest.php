<?php

namespace App\Http\Requests\Domain;

use Illuminate\Validation\Rule;

class StoreAddressRequest extends DomainRequest
{
    public function rules(): array
    {
        return ['recipient_name' => ['required', 'string', 'max:100'], 'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9][0-9 ()-]{6,19}$/'], 'address_line' => ['required', 'string', 'max:255'], 'ward' => ['required', 'string', 'max:100'], 'district' => ['nullable', 'string', 'max:100'], 'province' => ['required', 'string', 'max:100'], 'country_code' => ['sometimes', Rule::in(['VN'])], 'is_default' => ['sometimes', 'boolean']];
    }
}
