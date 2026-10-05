<?php

namespace App\Http\Requests\Domain;

class ReasonRequest extends DomainRequest
{
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:1000']];
    }
}
