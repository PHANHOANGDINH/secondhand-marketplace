<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Domain;
use App\Http\Resources\ProductImageResource;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ProductImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProductImageController extends Controller
{
    public function __construct(private ProductImageService $service) {}

    public function store(Domain\UploadProductImagesRequest $request, Product $product): JsonResponse
    {
        $saved = $this->service->upload($request->user(), $product, $request->file('images'));

        return ProductImageResource::collection($saved->images()->orderBy('sort_order')->orderBy('id')->get())->response()->setStatusCode(201);
    }

    public function update(Domain\UpdateProductImageRequest $request, Product $product, ProductImage $image): ProductImageResource
    {
        return new ProductImageResource($this->service->update($request->user(), $product, $image, $request->validated()));
    }

    public function reorder(Domain\ReorderProductImagesRequest $request, Product $product): AnonymousResourceCollection
    {
        $saved = $this->service->reorder($request->user(), $product, $request->validated('image_ids'));

        return ProductImageResource::collection($saved->images()->orderBy('sort_order')->orderBy('id')->get());
    }

    public function destroy(Domain\DomainRequest $request, Product $product, ProductImage $image): Response
    {
        $this->service->delete($request->user(), $product, $image);

        return response()->noContent();
    }
}
