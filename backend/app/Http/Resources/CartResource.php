<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource['cart']->id,
            'items' => CartItemResource::collection($this->resource['items']),
            'item_count' => count($this->resource['items']),
            'total_quantity' => $this->resource['total_quantity'],
            'subtotal' => $this->resource['subtotal'],
            'available_subtotal' => $this->resource['available_subtotal'],
            'has_unavailable_items' => collect($this->resource['items'])->contains(fn (array $row): bool => $row['reason'] !== null),
        ];
    }
}
