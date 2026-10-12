<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProductVideoStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Domain;
use App\Http\Resources\ProductVideoResource;
use App\Http\Resources\VideoDiscoveryResource;
use App\Models\Product;
use App\Models\ProductVideo;
use App\Services\FixedPriceEligibility;
use App\Services\ProductQuery;
use App\Services\ProductVideoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class ProductVideoController extends Controller
{
    public function __construct(private ProductVideoService $service) {}

    private function discoveryQuery(ProductQuery $query): Builder
    {
        return ProductVideo::where('status', ProductVideoStatus::Ready)
            ->whereIn('product_id', $query->publicVisible()->select('id'))
            ->with(['product' => fn (BelongsTo $products) => $query->related($products->getQuery()), 'product.shop:id,name,slug', 'product.category:id,name,slug', 'product.images' => fn (HasMany $q) => $q->orderBy('sort_order')->orderBy('id')]);
    }

    private function discoveryContext(Collection $videos): void
    {
        $auctions = app(FixedPriceEligibility::class)->claims()->whereIn('product_id', $videos->pluck('product_id'))
            ->latest('id')->get()->keyBy('product_id');
        foreach ($videos as $video) {
            $video->setRelation('discoveryAuction', $auctions->get($video->product_id));
        }
    }

    public function discovery(Domain\VideoDiscoveryRequest $request, ProductQuery $query): AnonymousResourceCollection
    {
        $videos = $this->discoveryQuery($query)->latest('id')->paginate($request->integer('per_page', 12));
        $this->discoveryContext($videos->getCollection());

        return VideoDiscoveryResource::collection($videos);
    }

    public function discoveryShow(string $video, ProductQuery $query): VideoDiscoveryResource
    {
        $row = $this->discoveryQuery($query)->whereKey($video)->firstOrFail();
        $this->discoveryContext(collect([$row]));

        return new VideoDiscoveryResource($row);
    }

    public function publicIndex(string $slug, ProductQuery $query): AnonymousResourceCollection
    {
        $product = $query->publicVisible()->where('slug', $slug)->firstOrFail();

        return ProductVideoResource::collection($product->videos()->where('status', ProductVideoStatus::Ready)->orderBy('sort_order')->orderBy('id')->get());
    }

    public function index(Domain\DomainRequest $request, Product $product): AnonymousResourceCollection
    {
        Gate::forUser($request->user())->authorize('view', $product);

        return ProductVideoResource::collection($product->videos()->orderBy('sort_order')->orderBy('id')->get())->additional(['limits' => ['max_files' => config('product-videos.max_files'), 'max_bytes' => config('product-videos.max_kilobytes') * 1024]]);
    }

    public function store(Domain\UploadProductVideoRequest $request, Product $product): JsonResponse
    {
        return (new ProductVideoResource($this->service->upload($request->user(), $product, $request->file('video'), $request->safe()->except('video'))))->response()->setStatusCode(201);
    }

    public function update(Domain\UpdateProductVideoRequest $request, Product $product, ProductVideo $video): ProductVideoResource
    {
        return new ProductVideoResource($this->service->update($request->user(), $product, $video, $request->validated()));
    }

    public function destroy(Domain\DomainRequest $request, Product $product, ProductVideo $video): Response
    {
        $this->service->delete($request->user(), $product, $video);

        return response()->noContent();
    }
}
