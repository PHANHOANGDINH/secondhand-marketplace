<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Domain;
use App\Http\Resources\ProductResource;
use App\Services\ProductQuery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PublicProductController extends Controller
{
    public function __construct(private ProductQuery $query) {}

    public function index(Domain\PublicProductListRequest $request): AnonymousResourceCollection
    {
        return ProductResource::collection($this->query->apply($this->query->publicVisible(), $request->validated(), true)->paginate($request->integer('per_page', 20))->withQueryString());
    }

    public function show(string $slug): ProductResource
    {
        return new ProductResource($this->query->related($this->query->publicVisible())->where('slug', $slug)->firstOrFail());
    }
}
