<?php

namespace App\Http\Requests\Domain;

class RecommendationListRequest extends DomainRequest
{
    public function rules(): array
    {
        return ['limit' => ['sometimes', 'integer', 'min:1', 'max:'.(int) config('recommendations.max_results')]];
    }
}
