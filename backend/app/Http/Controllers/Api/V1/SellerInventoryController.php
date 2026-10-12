<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Domain\DomainRequest;
use App\Http\Requests\Domain\SellerInventoryRequest;
use App\Http\Resources\ManagedProductResource;
use App\Services\ProductQuery;
use App\Services\SellerInventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SellerInventoryController extends Controller
{
    public function index(SellerInventoryRequest $request, SellerInventoryService $service, ProductQuery $products): AnonymousResourceCollection
    {
        $shop = $service->shop($request->user());

        return ManagedProductResource::collection($products->related($service->inventory($shop, $request->validated()))->paginate($request->integer('per_page', 20))->withQueryString())
            ->additional(['low_stock_threshold' => config('seller-inventory.low_stock_threshold')]);
    }

    public function summary(DomainRequest $request, SellerInventoryService $service): JsonResponse
    {
        return response()->json(['data' => $service->summary($service->shop($request->user()))]);
    }
}
