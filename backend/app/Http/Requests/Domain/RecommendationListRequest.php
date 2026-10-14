<?php

namespace App\Http\Requests\Domain;

use Illuminate\Validation\Rule;

class RecommendationListRequest extends DomainRequest
{
    public function rules(): array
    {
        return ['limit' => ['sometimes', 'integer', 'min:1', 'max:'.(int) config('recommendations.max_results')], 'province' => ['sometimes', 'string', Rule::in(array_map('strval', array_keys(config('provinces.list'))))]];
    }
}
