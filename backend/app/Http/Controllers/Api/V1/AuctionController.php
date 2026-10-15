<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Domain\AcceptAuctionBidRequest;
use App\Http\Requests\Domain\AuctionListRequest;
use App\Http\Requests\Domain\DomainRequest;
use App\Http\Requests\Domain\StoreAuctionRequest;
use App\Http\Requests\Domain\StoreBidRequest;
use App\Http\Resources\AuctionResource;
use App\Http\Resources\BidResource;
use App\Models\Auction;
use App\Services\AuctionService;
use App\Services\SellerInventoryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AuctionController extends Controller
{
    public function __construct(private AuctionService $service) {}

    public function index(AuctionListRequest $request): AnonymousResourceCollection
    {
        return AuctionResource::collection($this->service->publicQuery()->when($request->validated('status'), fn (Builder $q, string $status): Builder => $q->where('status', $status))->orderByDesc('id')->paginate($request->integer('per_page', 20))->withQueryString())->additional(['server_time' => now()->toISOString()]);
    }

    public function show(Auction $auction): AuctionResource
    {
        return new AuctionResource($this->service->publicQuery()->findOrFail($auction->id));
    }

    public function bids(AuctionListRequest $request, Auction $auction): AnonymousResourceCollection
    {
        $visible = $this->service->publicQuery()->findOrFail($auction->id);

        return BidResource::collection($visible->bids()->orderByDesc('id')->paginate($request->integer('per_page', 20))->withQueryString())->additional(['server_time' => now()->toISOString()]);
    }

    public function sellerIndex(AuctionListRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Auction::class);

        return AuctionResource::collection(Auction::where('seller_id', $request->user()->id)->with('product:id,name,slug')->withCount('bids')->when($request->validated('status'), fn (Builder $q, string $status): Builder => $q->where('status', $status))->when($request->boolean('live_now') || $request->boolean('ending_soon'), fn (Builder $q): Builder => app(SellerInventoryService::class)->liveAuctions($q, $request->boolean('ending_soon')))->orderByDesc('id')->paginate($request->integer('per_page', 20))->withQueryString())->additional(['server_time' => now()->toISOString()]);
    }

    public function sellerShow(Auction $auction): AuctionResource
    {
        Gate::authorize('view', $auction);

        return new AuctionResource($auction->load('product:id,name,slug')->loadCount('bids'));
    }

    public function store(StoreAuctionRequest $request): JsonResponse
    {
        return (new AuctionResource($this->service->create($request->user(), $request->validated())->load('product:id,name,slug')->loadCount('bids')))->response()->setStatusCode(201);
    }

    public function cancel(DomainRequest $request, Auction $auction): AuctionResource
    {
        return new AuctionResource($this->service->cancel($request->user(), $auction)->load('product:id,name,slug')->loadCount('bids'));
    }

    /** "Bán ngay": the seller ends a live auction by accepting its highest bid. */
    public function acceptBid(AcceptAuctionBidRequest $request, Auction $auction): AuctionResource
    {
        return new AuctionResource($this->service->acceptHighestBid($request->user(), $auction, $request->validated())->load('product:id,name,slug')->loadCount('bids'));
    }

    public function placeBid(StoreBidRequest $request, Auction $auction): JsonResponse
    {
        return (new BidResource($this->service->placeBid($request->user(), $auction, $request->validated('amount'))))->additional(['server_time' => now()->toISOString()])->response()->setStatusCode(201);
    }
}
