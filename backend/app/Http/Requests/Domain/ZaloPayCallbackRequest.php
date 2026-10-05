<?php

namespace App\Http\Requests\Domain;

class ZaloPayCallbackRequest extends DomainRequest
{
    public function rules(): array
    {
        return ['data' => ['required', 'string', 'max:20000'], 'mac' => ['required', 'string', 'regex:/^[a-fA-F0-9]{64}$/D'], 'type' => ['required', 'integer', 'in:1']];
    }
}
