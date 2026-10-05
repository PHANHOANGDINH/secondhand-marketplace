<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Domain\DomainRequest;
use App\Http\Requests\Domain\SellerOrderListRequest;
use App\Http\Requests\Domain\UpdateShopOrderRequest;
use App\Http\Resources\ShopOrderResource;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Services\ShopOrderService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class SellerOrderController extends Controller
{
    public function index(SellerOrderListRequest $request): AnonymousResourceCollection
    {
        $query = ShopOrder::whereIn('shop_id', Shop::withTrashed()->where('owner_id', $request->user()->id)->select('id'))->with(['shop', 'order.payments', 'orderItems']);
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->input('from').' 00:00:00');
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<', Carbon::parse($request->input('to'))->addDay());
        }

        return ShopOrderResource::collection($query->latest('id')->paginate($request->integer('per_page', 20))->withQueryString());
    }

    public function show(DomainRequest $request, ShopOrder $shopOrder): ShopOrderResource
    {
        Gate::authorize('view', $shopOrder);

        return new ShopOrderResource($shopOrder->load(['shop', 'order.payments', 'orderItems']));
    }

    public function updateStatus(UpdateShopOrderRequest $request, ShopOrder $shopOrder, ShopOrderService $service): ShopOrderResource
    {
        return new ShopOrderResource($service->transition($request->user(), $shopOrder, $request->validated()));
    }
}
