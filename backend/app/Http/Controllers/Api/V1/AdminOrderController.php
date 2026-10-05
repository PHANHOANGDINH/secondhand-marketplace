<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Domain;
use App\Http\Resources\AdminOrderResource;
use App\Models\Order;
use App\Services\AdminOrderQuery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AdminOrderController extends Controller
{
    public function index(Domain\AdminOrderListRequest $request, AdminOrderQuery $query): AnonymousResourceCollection
    {
        Gate::authorize('viewAdmin', Order::class);

        return AdminOrderResource::collection($query->apply($request->validated())->paginate($request->integer('per_page', 20))->withQueryString());
    }

    public function show(Domain\DomainRequest $request, Order $order): AdminOrderResource
    {
        Gate::authorize('viewAdmin', Order::class);

        return new AdminOrderResource($order->load([...AdminOrderQuery::RELATIONS, 'shopOrders.orderItems']));
    }
}
