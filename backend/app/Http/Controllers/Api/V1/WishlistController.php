<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Domain\DomainRequest;
use App\Http\Requests\Domain\WishlistListRequest;
use App\Http\Resources\ProductResource;
use App\Models\Wishlist;
use App\Services\ProductQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class WishlistController extends Controller
{
    public function __construct(private ProductQuery $query) {}

    public function index(WishlistListRequest $request): AnonymousResourceCollection
    {
        $userId = $request->user()->id;
        $query = $this->query->related($this->query->publicVisible())
            ->whereHas('wishlistEntries', fn (Builder $rows) => $rows->where('user_id', $userId))
            ->addSelect(['wishlist_entry_id' => Wishlist::select('id')->whereColumn('product_id', 'products.id')->where('user_id', $userId)->limit(1), 'wishlisted_at' => Wishlist::select('created_at')->whereColumn('product_id', 'products.id')->where('user_id', $userId)->limit(1)])
            ->orderByDesc('wishlisted_at')->orderByDesc('wishlist_entry_id');

        return ProductResource::collection($query->paginate($request->integer('per_page', 20))->withQueryString());
    }

    public function store(DomainRequest $request, string $product): JsonResponse
    {
        DB::transaction(function () use ($request, $product): void {
            $row = $this->query->publicVisible()->whereKey($product)->lockForUpdate()->firstOrFail();
            Wishlist::firstOrCreate(['user_id' => $request->user()->id, 'product_id' => $row->id]);
        }, 3);

        return response()->json(['data' => ['is_wishlisted' => true]]);
    }

    public function destroy(DomainRequest $request, string $product): JsonResponse
    {
        Wishlist::where('user_id', $request->user()->id)->where('product_id', $product)->delete();

        return response()->json(['data' => ['is_wishlisted' => false]]);
    }
}
