<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Domain\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Services\CheckoutService;
use App\Services\OrderSummary;
use App\Services\ZaloPayService;
use Illuminate\Http\JsonResponse;

class CheckoutController extends Controller
{
    public function __invoke(CheckoutRequest $request, CheckoutService $service): JsonResponse
    {
        $result = $service->checkout($request->user(), $request->integer('address_id'), strtolower($request->header('Idempotency-Key')), PaymentMethod::from($request->input('payment_method')), $request->input('preferred_payment_method', []), $request->input('cart_item_ids', []));

        if ($result['order']->payment_method === PaymentMethod::ZALOPAY) {
            app(ZaloPayService::class)->start($result['order']->payments->sortBy('attempt_number')->last());
            $result['order']->load(OrderSummary::RELATIONS);
        }

        return (new OrderResource($result['order']))->response()->setStatusCode($result['created'] ? 201 : 200);
    }
}
