<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Domain;
use App\Http\Resources\AdminAuctionResource;
use App\Models\Auction;
use App\Services\AdminActionCenterService;
use App\Services\AuctionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AdminAuctionController extends Controller
{
    private function listing(): Builder
    {
        return Auction::with(['product:id,name,slug,shop_id', 'seller:id,name,deleted_at'])->withCount('bids');
    }

    public function index(Domain\AdminAuctionListRequest $request, AdminActionCenterService $center): AnonymousResourceCollection
    {
        Gate::authorize('viewAdmin', Auction::class);
        $query = $this->listing();
        if ($request->filled('status')) {
            $query->where('status', $request->validated('status'));
        }
        if ($request->filled('settlement')) {
            $center->settlement($query, $request->validated('settlement'));
        }
        if ($request->boolean('overdue')) {
            $center->overdueAuctions($query);
        }
        if ($request->filled('auction')) {
            $query->whereKey($request->integer('auction'));
        }

        return AdminAuctionResource::collection($query->orderByDesc('id')->paginate($request->integer('per_page', 20))->withQueryString())->additional(['server_time' => now()->toISOString()]);
    }

    public function cancel(Domain\AdminCancelAuctionRequest $request, Auction $auction, AuctionService $service): AdminAuctionResource
    {
        $saved = $service->cancelByAdmin($request->user(), $auction, $request->validated());

        return new AdminAuctionResource($this->listing()->findOrFail($saved->id));
    }
}
