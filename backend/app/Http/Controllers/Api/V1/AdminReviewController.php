<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Domain;
use App\Http\Resources\AdminReviewResource;
use App\Models\Review;
use App\Services\ReviewService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AdminReviewController extends Controller
{
    public function index(Domain\AdminReviewListRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAdmin', Review::class);
        $query = Review::with(['user', 'product']);
        foreach (['status' => 'status', 'product' => 'product_id', 'rating' => 'rating'] as $filter => $column) {
            if ($request->has($filter)) {
                $query->where($column, $request->validated($filter));
            }
        }

        return AdminReviewResource::collection($query->latest('id')->paginate($request->integer('per_page', 20))->withQueryString());
    }

    public function show(Domain\DomainRequest $request, Review $review): AdminReviewResource
    {
        Gate::authorize('viewAdmin', Review::class);

        return new AdminReviewResource($review->load(['user', 'product']));
    }

    public function updateStatus(Domain\ModerateReviewRequest $request, Review $review, ReviewService $service): AdminReviewResource
    {
        return new AdminReviewResource($service->moderate($request->user(), $review, $request->validated()));
    }
}
