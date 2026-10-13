<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Domain;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Shop;
use App\Services\ProductQuery;
use App\Services\RecommendationSignals;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PublicProductController extends Controller
{
    public function __construct(private ProductQuery $query, private RecommendationSignals $signals) {}

    public function index(Domain\PublicProductListRequest $request): AnonymousResourceCollection
    {
        if ($request->integer('page', 1) === 1) {
            $this->signals->recordSearch($request->user('sanctum'), $request->validated('q'));
        }

        return ProductResource::collection($this->query->apply($this->query->publicVisible(), $request->validated(), true)->paginate($request->integer('per_page', 20))->withQueryString());
    }

    public function show(string $slug): ProductResource
    {
        $product = $this->query->related($this->query->publicVisible())->where('slug', $slug)->firstOrFail();
        $this->signals->recordView(request()->user('sanctum'), $product);

        return new ProductResource($product);
    }

    public function suggestions(Domain\ProductSuggestionsRequest $request): JsonResponse
    {
        $keyword = $request->validated('q');
        $products = $this->query->apply($this->query->publicVisible(), ['q' => $keyword, 'sort' => 'relevance'], true)->limit(5)->get();
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $keyword).'%';
        $categories = Category::whereIn('id', $this->query->publicVisible()->select('category_id'))
            ->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])->orderBy('name')->orderBy('id')->limit(3)->get(['id', 'name']);
        $shops = Shop::whereIn('id', $this->query->publicVisible()->select('shop_id'))
            ->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])->orderBy('name')->orderBy('id')->limit(3)->get(['id', 'name']);

        return response()->json(['data' => ['products' => ProductResource::collection($products), 'categories' => $categories, 'shops' => $shops]]);
    }
}
