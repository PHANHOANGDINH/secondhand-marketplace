<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, ...array_intersect_key($this->data, array_flip(['type', 'report_id', 'auction_id', 'product_id', 'product_name', 'message', 'target_route', 'winning_amount', 'checkout_deadline'])), 'created_at' => $this->created_at->toISOString(), 'read_at' => $this->read_at?->toISOString()];
    }
}
