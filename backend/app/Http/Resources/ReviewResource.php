<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => (string) $this->id, 'rating' => $this->rating, 'comment' => $this->comment, 'status' => $this->status->value,
            'author' => $this->whenLoaded('user', fn () => ['name' => $this->user && ! $this->user->trashed() ? $this->user->name : 'Người mua']),
            'created_at' => $this->created_at?->toISOString(), 'updated_at' => $this->updated_at?->toISOString()];
    }
}
