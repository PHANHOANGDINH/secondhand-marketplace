<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Domain;
use App\Http\Resources\ShopResource;
use App\Services\ShopService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PublicShopController extends Controller
{
    public function __construct(private ShopService $service) {}

    public function index(Domain\ShopListRequest $request): AnonymousResourceCollection
    {
        $query = $this->service->publicQuery();
        $search = $request->validated('search');
        if ($search !== null && $search !== '') {
            $query->whereRaw("name LIKE ? ESCAPE '!'", ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%']);
        }

        return ShopResource::collection($query->orderByDesc('id')->paginate($request->integer('per_page', 20))->withQueryString());
    }

    public function show(string $slug): ShopResource
    {
        return new ShopResource($this->service->publicQuery()->where('slug', $slug)->firstOrFail());
    }
}
