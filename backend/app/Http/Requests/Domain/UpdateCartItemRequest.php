<?php

namespace App\Http\Requests\Domain;

class UpdateCartItemRequest extends DomainRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('item'));
    }

    public function rules(): array
    {
        return ['quantity' => ['required', 'integer', 'min:1', 'max:99']];
    }
}
