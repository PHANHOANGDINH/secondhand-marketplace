<?php

namespace App\Http\Requests\Domain;

class SendMessageRequest extends DomainRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('send', $this->route('conversation'));
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('body'))) {
            $this->merge(['body' => preg_replace('/^[\s\p{Z}]+|[\s\p{Z}]+$/u', '', $this->input('body'))]);
        }
    }

    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:1000']];
    }
}
