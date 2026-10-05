<?php

namespace App\Http\Requests\Domain;

use App\Enums\ReviewStatus;
use Illuminate\Validation\Rule;

class AdminReviewListRequest extends DomainRequest
{
    public function rules(): array
    {
        return ['status' => ['sometimes', Rule::enum(ReviewStatus::class)], 'product' => ['sometimes', 'integer', 'min:1'], 'rating' => ['sometimes', 'integer', 'between:1,5'], 'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']];
    }
}
