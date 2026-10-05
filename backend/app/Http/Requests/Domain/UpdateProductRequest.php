<?php

namespace App\Http\Requests\Domain;

use Illuminate\Validation\Rule;

class UpdateProductRequest extends StoreProductRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('product'));
    }

    public function rules(): array
    {
        $rules = parent::rules();
        foreach ($rules as &$rule) {
            array_unshift($rule, 'sometimes');
        } unset($rule);
        $rules['expected_updated_at'] = [Rule::requiredIf($this->exists('stock_quantity')), 'date'];

        return $rules;
    }
}
