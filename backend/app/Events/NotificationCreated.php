<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;

class NotificationCreated extends AuctionBroadcastEvent
{
    public function __construct(private readonly int $recipientId, array $payload)
    {
        parent::__construct($payload);
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->recipientId)];
    }

    public function broadcastWhen(): bool
    {
        return true;
    }
}
