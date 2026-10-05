<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Domain;
use App\Http\Resources\ManagedShopResource;
use App\Models\Shop;
use App\Services\ShopService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AdminShopController extends Controller
{
    public function __construct(private ShopService $service) {}

    public function index(Domain\ShopListRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Shop::class);
        $query = Shop::withTrashed();
        if ($status = $request->validated('status')) {
            $query->where('status', $status);
        }
        $search = $request->validated('search');
        if ($search !== null && $search !== '') {
            $query->whereRaw("name LIKE ? ESCAPE '!'", ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%']);
        }

        return ManagedShopResource::collection($query->orderByDesc('id')->paginate($request->integer('per_page', 20))->withQueryString());
    }

    public function show(Shop $shop): ManagedShopResource
    {
        Gate::authorize('viewAdmin', $shop);

        return new ManagedShopResource($shop);
    }

    public function updateStatus(Domain\ModerateShopRequest $request, Shop $shop): ManagedShopResource
    {
        return new ManagedShopResource($this->service->moderate($request->user(), $shop, $request->validated()));
    }
}
