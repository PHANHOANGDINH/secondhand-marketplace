<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => (string) $this->id, 'name' => $this->name, 'slug' => $this->slug, 'description' => $this->description, 'parent_id' => $this->parent_id === null ? null : (string) $this->parent_id, 'status' => $this->status->value];
    }
}
