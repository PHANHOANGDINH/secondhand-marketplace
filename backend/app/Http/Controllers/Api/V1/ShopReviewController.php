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

    /** Signed in: may I rate this shop, and what did I give it? */
    public function mine(Request $request, string $slug): JsonResponse
    {
        $shop = $this->shops->publicQuery()->where('slug', $slug)->firstOrFail();
        $review = ShopReview::where('shop_id', $shop->id)->where('user_id', $request->user()->id)->first();

        return response()->json(['data' => [
            'can_review' => $this->service->eligible($request->user(), $shop),
            'own_shop' => $shop->owner_id === $request->user()->id,
            'review' => $review ? (new ShopReviewResource($review))->resolve() : null,
        ]]);
    }

    public function save(SaveShopReviewRequest $request, string $slug): JsonResponse
    {
        $shop = $this->shops->publicQuery()->where('slug', $slug)->firstOrFail();
        $existing = ShopReview::where('shop_id', $shop->id)->where('user_id', $request->user()->id)->exists();
        $review = $this->service->save($request->user(), $shop, $request->validated());

        return (new ShopReviewResource($review))->additional(['summary' => ShopReviewService::summary($shop->id)])->response()->setStatusCode($existing ? 200 : 201);
    }
}
