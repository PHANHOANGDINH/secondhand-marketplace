<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Domain\ReviewListRequest;
use App\Http\Requests\Domain\SaveShopReviewRequest;
use App\Http\Resources\ShopReviewResource;
use App\Models\ShopReview;
use App\Services\ShopReviewService;
use App\Services\ShopService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ShopReviewController extends Controller
{
    public function __construct(private ShopReviewService $service, private ShopService $shops) {}

    /** Public: published ratings of a public shop, newest first, with the star summary. */
    public function index(ReviewListRequest $request, string $slug): AnonymousResourceCollection
    {
        $shop = $this->shops->publicQuery()->where('slug', $slug)->firstOrFail();

        return ShopReviewResource::collection(ShopReviewService::published(ShopReview::where('shop_id', $shop->id))->with('user:id,name,deleted_at')->latest('id')->paginate($request->integer('per_page', 10))->withQueryString())
            ->additional(['summary' => ShopReviewService::summary($shop->id)]);
    }

    /** Signed in: how many completed orders here can I still rate, and which ratings did I give (any status)? */
    public function mine(Request $request, string $slug): JsonResponse
    {
        $shop = $this->shops->publicQuery()->where('slug', $slug)->firstOrFail();
        $user = $request->user();
        $own = $shop->owner_id === $user->id;
        $pending = $own ? 0 : $this->service->unrated($user, $shop)->count();
        $reviews = ShopReview::where('shop_id', $shop->id)->where('user_id', $user->id)->latest('id')->get();

        return response()->json(['data' => [
            'can_review' => $pending > 0,
            'own_shop' => $own,
            'pending_count' => $pending,
            'reviews' => ShopReviewResource::collection($reviews)->resolve(),
        ]]);
    }

    public function store(SaveShopReviewRequest $request, string $slug): JsonResponse
    {
        $shop = $this->shops->publicQuery()->where('slug', $slug)->firstOrFail();
        $review = $this->service->create($request->user(), $shop, $request->validated());

        return (new ShopReviewResource($review))->additional(['summary' => ShopReviewService::summary($shop->id)])->response()->setStatusCode(201);
    }

    public function update(SaveShopReviewRequest $request, ShopReview $shopReview): ShopReviewResource
    {
        // Only while the shop is public, as when rating it.
        $shop = $this->shops->publicQuery()->whereKey($shopReview->shop_id)->firstOrFail();
        $review = $this->service->update($request->user(), $shopReview, $request->validated());

        return (new ShopReviewResource($review))->additional(['summary' => ShopReviewService::summary($shop->id)]);
    }
}
