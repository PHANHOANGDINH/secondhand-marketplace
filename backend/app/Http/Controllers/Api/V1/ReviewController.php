<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Domain\ReviewListRequest;
use App\Http\Requests\Domain\StoreReviewRequest;
use App\Http\Requests\Domain\UpdateReviewRequest;
use App\Http\Resources\OrderItemResource;
use App\Http\Resources\ReviewResource;
use App\Models\OrderItem;
use App\Models\Review;
use App\Services\ProductQuery;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReviewController extends Controller
{
    public function __construct(private ReviewService $service, private ProductQuery $products) {}

    public function index(ReviewListRequest $request, string $slug): AnonymousResourceCollection
    {
        $product = $this->products->publicVisible()->where('slug', $slug)->firstOrFail();

        return ReviewResource::collection(ReviewService::published(Review::where('product_id', $product->id))->with('user:id,name,deleted_at')->latest('id')->paginate($request->integer('per_page', 20))->withQueryString());
    }

    public function eligibility(ReviewListRequest $request, string $slug): AnonymousResourceCollection
    {
        $product = $this->products->related($this->products->publicVisible())->where('slug', $slug)->firstOrFail();
        $items = $this->service->eligibleItems($request->user(), $product)->paginate($request->integer('per_page', 20))->withQueryString();
        foreach ($items as $item) {
            ReviewService::describeItem($item, $request->user(), true, $product->shop->owner_id);
        }

        return OrderItemResource::collection($items);
    }

    public function store(StoreReviewRequest $request, OrderItem $orderItem): JsonResponse
    {
        return (new ReviewResource($this->service->create($request->user(), $orderItem, $request->validated())))->response()->setStatusCode(201);
    }

    public function update(UpdateReviewRequest $request, Review $review): ReviewResource
    {
        return new ReviewResource($this->service->update($request->user(), $review, $request->validated()));
    }
}
