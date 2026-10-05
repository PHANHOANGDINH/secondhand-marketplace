<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Domain;
use App\Http\Resources\ManagedProductResource;
use App\Models\Product;
use App\Services\ProductQuery;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class SellerProductController extends Controller
{
    public function __construct(private ProductService $service, private ProductQuery $query) {}

    private function resource(Product $product): ManagedProductResource
    {
        return new ManagedProductResource($this->query->related(Product::query())->findOrFail($product->id));
    }

    public function index(Domain\SellerProductListRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Product::class);
        $shop = $request->user()->shop()->firstOrFail();

        return ManagedProductResource::collection($this->query->apply(Product::where('shop_id', $shop->id), $request->validated())->paginate($request->integer('per_page', 20))->withQueryString());
    }

    public function store(Domain\StoreProductRequest $request): JsonResponse
    {
        return $this->resource($this->service->create($request->user(), $request->validated()))->response()->setStatusCode(201);
    }

    public function show(Product $product): ManagedProductResource
    {
        Gate::authorize('view', $product);

        return $this->resource($product);
    }

    public function update(Domain\UpdateProductRequest $request, Product $product): ManagedProductResource
    {
        return $this->resource($this->service->update($request->user(), $product, $request->validated()));
    }

    public function destroy(Domain\DomainRequest $request, Product $product): Response
    {
        Gate::authorize('delete', $product);
        $this->service->delete($request->user(), $product);

        return response()->noContent();
    }

    public function submit(Domain\DomainRequest $request, Product $product): ManagedProductResource
    {
        return $this->resource($this->service->action($request->user(), $product, 'submit'));
    }

    public function hide(Domain\DomainRequest $request, Product $product): ManagedProductResource
    {
        return $this->resource($this->service->action($request->user(), $product, 'hide'));
    }

    public function withdraw(Domain\DomainRequest $request, Product $product): ManagedProductResource
    {
        return $this->resource($this->service->action($request->user(), $product, 'withdraw'));
    }

    public function revise(Domain\DomainRequest $request, Product $product): ManagedProductResource
    {
        return $this->resource($this->service->action($request->user(), $product, 'revise'));
    }
}
