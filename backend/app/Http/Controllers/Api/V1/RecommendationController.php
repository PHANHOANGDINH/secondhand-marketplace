<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Domain\RecommendationListRequest;
use App\Http\Resources\ProductResource;
use App\Services\ProductQuery;
use App\Services\RecommendationService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RecommendationController extends Controller
{
    public function __construct(private RecommendationService $recommendations, private ProductQuery $query) {}

    public function index(RecommendationListRequest $request): AnonymousResourceCollection
    {
        return ProductResource::collection($this->recommendations->forUser($request->user('sanctum'), $request->integer('limit', 12), $request->input('province')));
    }

    public function similar(RecommendationListRequest $request, string $slug): AnonymousResourceCollection
    {
        $product = $this->query->publicVisible()->where('slug', $slug)->firstOrFail();

        return ProductResource::collection($this->recommendations->similar($product, $request->integer('limit', 8), $request->input('province')));
    }
}
