<?php

namespace App\Http\Resources;

use App\Services\AuctionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuctionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['settlement_state' => $this->resource->settlementState(), 'winner_checkout_expires_at' => $this->winner_checkout_expires_at?->toISOString(), 'id' => (string) $this->id, 'product_id' => (string) $this->product_id, 'seller_id' => (string) $this->seller_id, 'product' => $this->whenLoaded('product', fn (): array => ['id' => (string) $this->product->id, 'name' => $this->product->name, 'slug' => $this->product->slug]), 'start_price' => $this->start_price, 'current_price' => $this->current_price, 'bid_count' => (int) $this->bids_count, 'minimum_next_bid' => AuctionService::minimumNextBid($this->resource, (int) $this->bids_count), 'bid_increment' => $this->bid_increment, 'starts_at' => $this->starts_at->toISOString(), 'ends_at' => $this->ends_at->toISOString(), 'status' => $this->status->value, 'winner_user_id' => $this->winner_user_id ? (string) $this->winner_user_id : null, 'winning_bid_id' => $this->winning_bid_id ? (string) $this->winning_bid_id : null, 'server_time' => now()->toISOString(), 'created_at' => $this->created_at->toISOString(), 'updated_at' => $this->updated_at->toISOString()];
    }
}
