<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $participant = $this->participants->firstWhere('user_id', '!=', $request->user()->id);
        $product = $this->resource->getRelation('visibleProduct');

        return [
            'id' => (string) $this->id,
            'partner' => ['id' => (string) $participant->user_id, 'name' => $participant->user?->name ?? 'Người dùng không còn hoạt động'],
            'product' => $product ? ['id' => (string) $product->id, 'name' => $product->name, 'slug' => $product->slug, 'price' => $product->price] : null,
            'product_unavailable' => $this->product_id !== null && $product === null,
            'last_message' => $this->latestMessage ? new MessageResource($this->latestMessage) : null,
            'last_message_at' => $this->last_message_at?->toISOString(),
            'unread_count' => (int) $this->unread_count,
        ];
    }
}
