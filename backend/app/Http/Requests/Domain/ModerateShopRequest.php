<?php

namespace App\Http\Requests\Domain;

use App\Enums\ShopStatus;
use Illuminate\Validation\Rule;

class ModerateShopRequest extends DomainRequest
{
    public function rules(): array
    {
        return ['expected_status' => ['required', Rule::enum(ShopStatus::class)], 'status' => ['required', Rule::enum(ShopStatus::class)], 'reason' => ['required', 'string', 'max:1000']];
    }

    public function authorize(): bool
    {
        return $this->user()->can('moderate', $this->route('shop'));
    }
}
