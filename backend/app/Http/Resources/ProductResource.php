<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'is_wishlisted' => (bool) ($this->resource->getAttributes()['is_wishlisted'] ?? false),
            'wishlisted_at' => $this->whenHas('wishlisted_at'),
            'reason_label' => $this->whenHas('reason_label'),
            'id' => (string) $this->id, 'name' => $this->name, 'slug' => $this->slug, 'description' => $this->description, 'price' => $this->price,
            'average_rating' => $this->whenHas('average_rating', fn () => $this->average_rating === null ? null : round((float) $this->average_rating, 2)),
            'review_count' => $this->whenHas('review_count', fn () => (int) $this->review_count),
            'stock_quantity' => $this->stock_quantity, 'condition' => $this->condition->value,
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'primary_image' => $this->whenLoaded('images', fn () => ($primary = $this->images->firstWhere('is_primary', true)) ? new ProductImageResource($primary) : null),
            'shop' => $this->whenLoaded('shop', fn () => $this->shop ? ['id' => (string) $this->shop->id, 'name' => $this->shop->name, 'slug' => $this->shop->slug] : null),
            'category' => $this->whenLoaded('category', fn () => $this->category ? ['id' => (string) $this->category->id, 'name' => $this->category->name, 'slug' => $this->category->slug] : null),
            'auction' => $this->whenLoaded('auctionClaim', fn () => $this->auctionClaim ? ['id' => (string) $this->auctionClaim->id, 'status' => $this->auctionClaim->status->value, 'current_price' => $this->auctionClaim->current_price] : null),
            'created_at' => $this->created_at?->toISOString(), 'published_at' => $this->published_at?->toISOString(),
        ];
    }
}
