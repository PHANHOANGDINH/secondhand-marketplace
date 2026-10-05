<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $item = $this->resource['item'];
        $product = $item->product;
        $shop = $product?->shop;
        $primary = $product?->images->first();
        $reason = $this->resource['reason'];

        return [
            'id' => (string) $item->id, 'quantity' => $item->quantity,
            'unit_price' => $product?->price, 'subtotal' => $this->resource['subtotal'],
            'available' => $reason === null, 'unavailable_reason' => $reason,
            'max_quantity' => $this->resource['public'] && $reason !== 'OWN_SHOP' ? min(99, $product->stock_quantity) : 0,
            'product' => $product ? ['id' => (string) $product->id, 'name' => $product->name, 'slug' => $product->slug, 'condition' => $product->condition->value, 'is_public' => $this->resource['public']] : null,
            'shop' => $shop ? ['id' => (string) $shop->id, 'name' => $shop->name, 'slug' => $shop->slug] : null,
            'primary_image' => $primary ? new ProductImageResource($primary) : null,
        ];
    }
}
