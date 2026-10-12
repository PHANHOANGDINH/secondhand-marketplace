<?php

namespace App\Events;

use App\Models\Auction;
use App\Services\ProductQuery;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

abstract class AuctionBroadcastEvent implements ShouldBroadcastNow, ShouldDispatchAfterCommit, ShouldRescue
{
    public function __construct(protected readonly array $payload) {}

    public function broadcastOn(): array
    {
        return [new Channel('auction.'.$this->payload['auction_id'])];
    }

    public function broadcastAs(): string
    {
        return class_basename($this);
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }

    public function broadcastWhen(): bool
    {
        $productId = Auction::whereKey($this->payload['auction_id'])->value('product_id');

        return $productId !== null && app(ProductQuery::class)->publicVisible()->whereKey($productId)->exists();
    }
}
