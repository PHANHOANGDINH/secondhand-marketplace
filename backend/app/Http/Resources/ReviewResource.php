<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => (string) $this->id, 'rating' => $this->rating, 'comment' => $this->comment, 'status' => $this->status->value,
            'video' => $this->video_path ? ['url' => Storage::disk('public')->url($this->video_path), 'mime_type' => $this->video_mime_type, 'size' => $this->video_size] : null,
            'author' => $this->whenLoaded('user', fn () => ['name' => $this->user && ! $this->user->trashed() ? $this->user->name : 'Người mua']),
            'created_at' => $this->created_at?->toISOString(), 'updated_at' => $this->updated_at?->toISOString()];
    }
}
