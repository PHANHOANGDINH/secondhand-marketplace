<?php

namespace App\Http\Requests\Domain;

use Illuminate\Validation\Rule;

class HideVideoCommentRequest extends DomainRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hide', $this->route('comment'));
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::in(['HIDDEN'])]];
    }
}
