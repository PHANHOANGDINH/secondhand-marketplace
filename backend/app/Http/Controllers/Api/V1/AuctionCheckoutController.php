<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Domain\AuctionCheckoutRequest;
use App\Http\Resources\AddressResource;
use App\Http\Resources\AuctionResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\ProductResource;
use App\Models\Auction;
use App\Services\AuctionCheckoutService;
use App\Services\OrderSummary;
use App\Services\ProductQuery;
use App\Services\ZaloPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AuctionCheckoutController extends Controller
{
    public function show(Request $request, Auction $auction): JsonResponse
    {
        Gate::authorize('checkout', $auction);
        $auction->load(['product', 'winningBid', 'winnerOrder'])->loadCount('bids');
        $order = $auction->winnerOrder?->load(OrderSummary::RELATIONS);
        $available = app(ProductQuery::class)->publicVisible()->whereKey($auction->product_id)->exists() && $auction->product->shop->owner_id === $auction->seller_id;

        return response()->json(['data' => ['auction' => new AuctionResource($auction), 'winning_price' => $auction->winningBid?->amount, 'product' => new ProductResource($auction->product->load(['images', 'shop', 'category'])), 'addresses' => AddressResource::collection($request->user()->addresses()->orderByDesc('is_default')->orderBy('id')->get()), 'checkout_deadline' => $auction->winner_checkout_expires_at?->toISOString(), 'remaining_seconds' => $auction->winner_checkout_expires_at ? max(0, (int) now()->diffInSeconds($auction->winner_checkout_expires_at, false)) : 0, 'payment_methods' => preg_match('/^[0-9]+\.00$/D', $auction->winningBid?->amount ?? '') ? ['COD', 'ZALOPAY'] : ['COD'], 'product_available' => $available, 'winner_order' => $order ? new OrderResource($order) : null]]);
    }

    public function store(AuctionCheckoutRequest $request, Auction $auction, AuctionCheckoutService $service): JsonResponse
    {
        $result = $service->checkout($request->user(), $auction, $request->integer('address_id'), strtolower($request->header('Idempotency-Key')), PaymentMethod::from($request->input('payment_method')), $request->input('preferred_payment_method', []));
        if ($result['order']->payment_method === PaymentMethod::ZALOPAY) {
            app(ZaloPayService::class)->start($result['order']->payments->sortBy('attempt_number')->last());
            $result['order']->load(OrderSummary::RELATIONS);
        }

        return (new OrderResource($result['order']))->response()->setStatusCode($result['created'] ? 201 : 200);
    }
}
