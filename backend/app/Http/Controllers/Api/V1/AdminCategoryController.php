<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Domain;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class AdminCategoryController extends Controller
{
    public function __construct(private CategoryService $service) {}

    public function index(Domain\CategoryListRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Category::class);
        $query = Category::query();
        if ($request->exists('parent_id')) {
            $query->where('parent_id', $request->validated('parent_id'));
        }

        return CategoryResource::collection($query->orderBy('id')->paginate($request->integer('per_page', 20))->withQueryString());
    }

    public function store(Domain\StoreCategoryRequest $request): JsonResponse
    {
        Gate::authorize('create', Category::class);

        return (new CategoryResource($this->service->save($request->user(), null, $request->validated())))->response()->setStatusCode(201);
    }

    public function update(Domain\UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        Gate::authorize('update', $category);

        return new CategoryResource($this->service->save($request->user(), $category, $request->validated()));
    }

    public function destroy(Domain\ReasonRequest $request, Category $category): Response
    {
        Gate::authorize('delete', $category);
        $this->service->delete($request->user(), $category, $request->validated('reason'));

        return response()->noContent();
    }
}
