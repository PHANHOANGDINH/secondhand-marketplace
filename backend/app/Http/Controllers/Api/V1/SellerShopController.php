<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Domain;
use App\Http\Resources\ManagedShopResource;
use App\Models\Shop;
use App\Services\ShopService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SellerShopController extends Controller
{
    public function __construct(private ShopService $service) {}

    public function show(Request $request): ManagedShopResource
    {
        $shop = $request->user()->shop()->firstOrFail();
        Gate::authorize('view', $shop);

        return new ManagedShopResource($shop);
    }

    public function store(Domain\StoreShopRequest $request): JsonResponse
    {
        Gate::authorize('create', Shop::class);

        return (new ManagedShopResource($this->service->create($request->user(), $request->validated())))->response()->setStatusCode(201);
    }

    public function update(Domain\UpdateShopRequest $request): ManagedShopResource
    {
        $shop = $request->user()->shop()->firstOrFail();
        Gate::authorize('update', $shop);

        return new ManagedShopResource($this->service->update($request->user(), $shop, $request->validated()));
    }

    public function submit(Domain\DomainRequest $request): ManagedShopResource
    {
        $shop = $request->user()->shop()->firstOrFail();
        Gate::authorize('submit', $shop);

        return new ManagedShopResource($this->service->submit($request->user(), $shop));
    }
}
