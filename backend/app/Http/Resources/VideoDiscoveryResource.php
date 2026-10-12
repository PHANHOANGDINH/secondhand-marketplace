<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class VideoDiscoveryResource extends ProductVideoResource
{
    public function toArray(Request $request): array
    {
        $auction = $this->resource->getRelation('discoveryAuction');

        return [...parent::toArray($request),
            'product' => new ProductResource($this->product),
            'auction' => $auction ? [
                'id' => (string) $auction->id, 'status' => $auction->status->value,
                'current_price' => $auction->current_price, 'starts_at' => $auction->starts_at->toISOString(),
                'ends_at' => $auction->ends_at->toISOString(),
            ] : null,
            'server_time' => now()->toISOString(),
        ];
    }
}
