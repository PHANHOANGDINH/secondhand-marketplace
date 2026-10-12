<?php

namespace App\Events;

use App\Services\VideoCommentService;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

abstract class VideoCommentBroadcastEvent implements ShouldBroadcastNow, ShouldDispatchAfterCommit, ShouldRescue
{
    public function __construct(protected readonly array $payload) {}

    public function broadcastOn(): array
    {
        return [new Channel('video.'.$this->payload['video_id'])];
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
        return app(VideoCommentService::class)->isPublic((int) $this->payload['video_id']);
    }
}
