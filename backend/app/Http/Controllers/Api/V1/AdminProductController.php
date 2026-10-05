<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Domain;
use App\Http\Resources\ManagedProductResource;
use App\Models\Product;
use App\Services\ProductQuery;
use App\Services\ProductService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AdminProductController extends Controller
{
    public function __construct(private ProductService $service, private ProductQuery $query) {}

    public function index(Domain\AdminProductListRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAdmin', Product::class);

        return ManagedProductResource::collection($this->query->apply(Product::withTrashed(), $request->validated())->paginate($request->integer('per_page', 20))->withQueryString());
    }

    public function show(Product $product): ManagedProductResource
    {
        Gate::authorize('viewAdmin', Product::class);

        return new ManagedProductResource($this->query->related(Product::withTrashed())->findOrFail($product->id));
    }

    public function updateStatus(Domain\ModerateProductRequest $request, Product $product): ManagedProductResource
    {
        $saved = $this->service->moderate($request->user(), $product, $request->validated());

        return new ManagedProductResource($this->query->related(Product::query())->findOrFail($saved->id));
    }
}
