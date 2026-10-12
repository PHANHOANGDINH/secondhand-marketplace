<?php

namespace App\Http\Requests\Domain;

class ReadConversationRequest extends DomainRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('conversation'));
    }

    public function rules(): array
    {
        return ['last_message_id' => ['required', 'integer', 'min:0']];
    }
}
