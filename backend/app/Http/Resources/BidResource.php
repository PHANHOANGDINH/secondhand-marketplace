<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BidResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => (string) $this->id, 'auction_id' => (string) $this->auction_id, 'user_id' => (string) $this->user_id, 'amount' => $this->amount, 'created_at' => $this->created_at->toISOString()];
    }
}
