<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'sender_id' => (string) $this->sender_id,
            'body' => $this->body,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
