<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Domain\CheckoutPreviewRequest;
use App\Http\Requests\Domain\VoucherListRequest;
use App\Http\Requests\Domain\VoucherRequest;
use App\Http\Resources\VoucherResource;
use App\Models\Shop;
use App\Models\Voucher;
use App\Services\VoucherManagementService;
use App\Services\VoucherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class VoucherController extends Controller
{
    public function __construct(private VoucherManagementService $vouchers) {}

    /** POST /checkout/preview: prices the cart with the given codes. Writes nothing. */
    public function preview(CheckoutPreviewRequest $request, VoucherService $pricing): JsonResponse
    {
        return response()->json(['data' => $pricing->preview($request->user(), $request->input('cart_item_ids', []), $request->input('platform_voucher'), $request->input('shop_vouchers', []))]);
    }

    // ---- Seller: vouchers of the signed-in user's own shop only.

    private function shop(Request $request): Shop
    {
        $shop = $request->user()->shop()->firstOrFail();
        Gate::authorize('view', $shop);

        return $shop;
    }

    /** A voucher id from another shop (or a platform voucher) is simply not found here. */
    private function own(Request $request, string $voucher): Voucher
    {
        $row = Voucher::where('scope', Voucher::SHOP)->where('shop_id', $this->shop($request)->id)->findOrFail($voucher);
        Gate::authorize('update', $row);

        return $row;
    }

    public function sellerIndex(VoucherListRequest $request): AnonymousResourceCollection
    {
        $query = $this->vouchers->query()->where('scope', Voucher::SHOP)->where('shop_id', $this->shop($request)->id)
            ->when($request->filled('status'), fn ($rows) => $rows->where('status', $request->input('status')));

        return VoucherResource::collection($query->paginate($request->integer('per_page', 20))->withQueryString());
    }

    public function sellerStore(VoucherRequest $request): JsonResponse
    {
        $shop = $this->shop($request);
        Gate::authorize('update', $shop);

        return (new VoucherResource($this->vouchers->create($request->user(), $shop, $request->validated())))->response()->setStatusCode(201);
    }

    public function sellerUpdate(VoucherRequest $request, string $voucher): VoucherResource
    {
        return new VoucherResource($this->vouchers->update($request->user(), $this->own($request, $voucher), $request->validated()));
    }

    public function sellerDestroy(Request $request, string $voucher): VoucherResource
    {
        return new VoucherResource($this->vouchers->deactivate($request->user(), $this->own($request, $voucher)));
    }

    // ---- Admin: sees every voucher, manages platform vouchers.

    public function adminIndex(VoucherListRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAdmin', Voucher::class);
        $query = $this->vouchers->query()
            ->when($request->filled('scope'), fn ($rows) => $rows->where('scope', $request->input('scope')))
            ->when($request->filled('status'), fn ($rows) => $rows->where('status', $request->input('status')));

        return VoucherResource::collection($query->paginate($request->integer('per_page', 20))->withQueryString());
    }

    public function adminStore(VoucherRequest $request): JsonResponse
    {
        Gate::authorize('createPlatform', Voucher::class);

        return (new VoucherResource($this->vouchers->create($request->user(), null, $request->validated())))->response()->setStatusCode(201);
    }

    public function adminUpdate(VoucherRequest $request, string $voucher): VoucherResource
    {
        $row = Voucher::where('scope', Voucher::PLATFORM)->findOrFail($voucher);
        Gate::authorize('update', $row);

        return new VoucherResource($this->vouchers->update($request->user(), $row, $request->validated()));
    }

    public function adminDestroy(Request $request, string $voucher): VoucherResource
    {
        $row = Voucher::where('scope', Voucher::PLATFORM)->findOrFail($voucher);
        Gate::authorize('delete', $row);

        return new VoucherResource($this->vouchers->deactivate($request->user(), $row));
    }
}
