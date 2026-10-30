<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

// Signals on each recipient's private user channel. REST stays authoritative: clients refetch on receipt.
abstract class MessagingBroadcastEvent implements ShouldBroadcastNow, ShouldDispatchAfterCommit, ShouldRescue
{
    /** @param list<int> $recipientIds */
    public function __construct(private readonly array $recipientIds, protected readonly array $payload) {}

    public function broadcastOn(): array
    {
        return array_map(fn (int $id) => new PrivateChannel('user.'.$id), array_values(array_unique($this->recipientIds)));
    }

    public function broadcastAs(): string
    {
        return class_basename($this);
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
