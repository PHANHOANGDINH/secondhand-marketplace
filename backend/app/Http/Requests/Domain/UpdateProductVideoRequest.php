<?php

namespace App\Http\Requests\Domain;

use Illuminate\Validation\Validator;

class UpdateProductVideoRequest extends DomainRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('product'));
    }

    public function rules(): array
    {
        return ['title' => ['sometimes', 'nullable', 'string', 'max:200'], 'description' => ['sometimes', 'nullable', 'string', 'max:1000'], 'is_primary' => ['sometimes', 'accepted'], 'sort_order' => ['sometimes', 'integer', 'min:0', 'max:2'], 'status' => ['sometimes', 'in:READY,HIDDEN']];
    }

    public function after(): array
    {
        return [...parent::after(), function (Validator $validator): void {
            if (! $this->all()) {
                $validator->errors()->add('video', 'Supply a metadata or video action.');
            } if ($this->exists('is_primary') && $this->input('status') === 'HIDDEN') {
                $validator->errors()->add('is_primary', 'A hidden video cannot be primary.');
            }
        }];
    }
}
