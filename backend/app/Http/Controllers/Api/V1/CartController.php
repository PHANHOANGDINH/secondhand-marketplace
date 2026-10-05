<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Domain\DomainRequest;
use App\Http\Requests\Domain\StoreCartItemRequest;
use App\Http\Requests\Domain\UpdateCartItemRequest;
use App\Http\Resources\CartItemResource;
use App\Http\Resources\CartResource;
use App\Models\CartItem;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class CartController extends Controller
{
    public function __construct(private CartService $service) {}

    public function show(DomainRequest $request): CartResource
    {
        return new CartResource($this->service->read($request->user()));
    }

    public function store(StoreCartItemRequest $request): JsonResponse
    {
        $result = $this->service->add($request->user(), $request->integer('product_id'), $request->integer('quantity'));

        return (new CartItemResource($result['item']))->response()->setStatusCode($result['created'] ? 201 : 200);
    }

    public function update(UpdateCartItemRequest $request, CartItem $item): CartItemResource
    {
        return new CartItemResource($this->service->update($request->user(), $item, $request->integer('quantity')));
    }

    public function destroy(DomainRequest $request, CartItem $item): Response
    {
        $this->service->remove($request->user(), $item);

        return response()->noContent();
    }

    public function clear(DomainRequest $request): Response
    {
        $this->service->clear($request->user());

        return response()->noContent();
    }
}
