<?php

namespace App\Http\Requests\Domain;

use App\Enums\ReviewStatus;
use Illuminate\Validation\Rule;

class ModerateReviewRequest extends DomainRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('moderate', $this->route('review'));
    }

    public function rules(): array
    {
        return ['expected_status' => ['required', Rule::enum(ReviewStatus::class)], 'status' => ['required', Rule::enum(ReviewStatus::class)], 'reason' => ['required', 'string', 'max:1000']];
    }
}
