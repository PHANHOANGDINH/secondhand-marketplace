<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Domain\CancelShopOrderRequest;
use App\Http\Requests\Domain\DomainRequest;
use App\Http\Requests\Domain\OrderListRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Models\ShopOrder;
use App\Services\OrderSummary;
use App\Services\ShopOrderService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    public function index(OrderListRequest $request): AnonymousResourceCollection
    {
        return OrderResource::collection(OrderSummary::filter(Order::where('buyer_id', $request->user()->id), $request->input('overall_status'))->with(OrderSummary::RELATIONS)->latest('id')->paginate($request->integer('per_page', 20))->withQueryString());
    }

    public function show(DomainRequest $request, Order $order): OrderResource
    {
        Gate::authorize('view', $order);

        return new OrderResource($order->load(OrderSummary::RELATIONS));
    }

    public function payments(OrderListRequest $request, Order $order): AnonymousResourceCollection
    {
        Gate::authorize('view', $order);

        return PaymentResource::collection($order->payments()->orderBy('attempt_number')->paginate($request->integer('per_page', 20))->withQueryString());
    }

    public function cancel(CancelShopOrderRequest $request, Order $order, ShopOrder $shopOrder, ShopOrderService $service): OrderResource
    {
        Gate::authorize('view', $order);
        abort_unless($shopOrder->order_id === $order->id, 404);
        $service->transition($request->user(), $shopOrder, [...$request->validated(), 'status' => 'CANCELLED'], true);

        return new OrderResource($order->load(OrderSummary::RELATIONS));
    }
}
