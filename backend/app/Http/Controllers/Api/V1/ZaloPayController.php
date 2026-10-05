<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Domain\DomainRequest;
use App\Http\Requests\Domain\RetryZaloPayRequest;
use App\Http\Requests\Domain\ZaloPayCallbackRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderSummary;
use App\Services\ZaloPayService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ZaloPayController extends Controller
{
    public function retry(RetryZaloPayRequest $request, Order $order, ZaloPayService $service): OrderResource
    {
        $channels = $request->input('preferred_payment_method', []);
        sort($channels);
        $service->retry($request->user(), $order, strtolower($request->header('Idempotency-Key')), $channels);

        return new OrderResource($order->load(OrderSummary::RELATIONS));
    }

    public function sync(DomainRequest $request, Order $order, ZaloPayService $service): OrderResource
    {
        Gate::authorize('view', $order);
        abort_unless($order->payment_method === PaymentMethod::ZALOPAY, 409, 'This order does not use ZaloPay.');
        $payment = $order->payments()->latest('attempt_number')->firstOrFail();
        $service->sync($payment);

        return new OrderResource($order->load(OrderSummary::RELATIONS));
    }

    public function callback(ZaloPayCallbackRequest $request, ZaloPayService $service): JsonResponse
    {
        try {
            $code = $service->callback($request->validated());
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) !== 1062) {
                throw $exception;
            }
            $code = -1;
        }

        return response()->json(['return_code' => $code, 'return_message' => $code === 1 ? 'Accepted' : 'Invalid payment']);
    }
}
