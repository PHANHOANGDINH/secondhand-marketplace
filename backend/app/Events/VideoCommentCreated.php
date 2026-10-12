<?php

namespace App\Events;

use App\Enums\VideoCommentStatus;
use App\Models\VideoComment;

class VideoCommentCreated extends VideoCommentBroadcastEvent
{
    public function broadcastWhen(): bool
    {
        return parent::broadcastWhen() && VideoComment::whereKey($this->payload['comment']['id'])->where('status', VideoCommentStatus::Published)->exists();
    }
}
