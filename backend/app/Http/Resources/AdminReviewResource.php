<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class AdminReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => (string) $this->id, 'rating' => $this->rating, 'comment' => $this->comment, 'status' => $this->status->value, 'video' => $this->video_path ? ['url' => Storage::disk('public')->url($this->video_path), 'mime_type' => $this->video_mime_type, 'size' => $this->video_size] : null, 'moderation_reason' => $this->moderation_reason, 'author' => ['id' => (string) $this->user_id, 'name' => $this->user && ! $this->user->trashed() ? $this->user->name : 'Người mua'], 'product' => ['id' => (string) $this->product_id, 'name' => $this->product->name, 'slug' => $this->product->slug, 'archived' => $this->product->trashed()], 'created_at' => $this->created_at?->toISOString()];
    }
}
