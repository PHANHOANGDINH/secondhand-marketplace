<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VideoCommentResource extends JsonResource
{
    public function publicData(): array
    {
        $author = $this->user;

        return ['id' => (string) $this->id, 'body' => $this->body, 'created_at' => $this->created_at->toISOString(),
            'author' => ['id' => $author && ! $author->trashed() ? (string) $author->id : null, 'name' => $author && ! $author->trashed() ? $author->name : 'Người dùng']];
    }

    public function toArray(Request $request): array
    {
        return [...$this->publicData(), 'can_manage' => (bool) $request->user()?->can('hide', $this->resource)];
    }
}
