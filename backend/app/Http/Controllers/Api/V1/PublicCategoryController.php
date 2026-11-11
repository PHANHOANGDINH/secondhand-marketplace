<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Domain;
use App\Http\Resources\CategoryResource;
use App\Services\CategoryService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PublicCategoryController extends Controller
{
    public function __construct(private CategoryService $service) {}

    public function index(Domain\CategoryListRequest $request): AnonymousResourceCollection
    {
        // The whole tree in one request: menus and filters need all of it, level by level would be one request per category.
        if ($request->boolean('all')) {
            return CategoryResource::collection($this->service->publicQuery()->orderBy('id')->get());
        }
        $query = $this->service->publicQuery()->where('parent_id', $request->validated('parent_id'));

        return CategoryResource::collection($query->orderBy('id')->paginate($request->integer('per_page', 20))->withQueryString());
    }

    public function show(string $slug): CategoryResource
    {
        return new CategoryResource($this->service->publicQuery()->where('slug', $slug)->firstOrFail());
    }
}
