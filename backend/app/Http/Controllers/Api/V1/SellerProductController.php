<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Domain;
use App\Http\Resources\ManagedProductResource;
use App\Models\Product;
use App\Services\FixedPriceEligibility;
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

        $products = Product::where('shop_id', $shop->id)->when($request->boolean('auction'), fn ($query) => $query->whereHas('auctionClaim'));

        return ManagedProductResource::collection($this->query->apply($products, $request->validated())->paginate($request->integer('per_page', 20))->withQueryString());
    }

    /** Tab counters for "Quản lý tin": every status plus products held by an auction, in one aggregate query. */
    public function counts(Domain\DomainRequest $request, FixedPriceEligibility $eligibility): JsonResponse
    {
        Gate::authorize('viewAny', Product::class);
        $shop = $request->user()->shop()->firstOrFail();
        $claims = $eligibility->claims()->whereColumn('auctions.product_id', 'products.id')->selectRaw('1');
        $sums = implode(', ', array_map(fn (ProductStatus $status): string => "COALESCE(SUM(status = '{$status->value}'), 0) AS `{$status->value}`", ProductStatus::cases()));
        $row = Product::where('shop_id', $shop->id)->toBase()->selectRaw($sums.', COALESCE(SUM(EXISTS('.$claims->toSql().')), 0) AS `AUCTION`, COUNT(*) AS `ALL`', $claims->getBindings())->first();

        return response()->json(['data' => array_map('intval', (array) $row)]);
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

    /** A listing an admin took down asks to come back; an admin decides. */
    public function reopen(Domain\DomainRequest $request, Product $product): ManagedProductResource
    {
        return $this->resource($this->service->action($request->user(), $product, 'reopen'));
    }
}
