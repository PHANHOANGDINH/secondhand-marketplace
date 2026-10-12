<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductVideoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = ['id' => (string) $this->id, 'title' => $this->title, 'description' => $this->description, 'url' => Storage::disk('public')->url($this->path), 'poster_url' => $this->poster_path ? Storage::disk('public')->url($this->poster_path) : null, 'duration_seconds' => $this->duration_seconds, 'is_primary' => $this->is_primary, 'sort_order' => $this->sort_order];
        if ($request->is('api/v1/seller/*')) {
            $data += ['status' => $this->status->value, 'mime_type' => $this->mime_type, 'file_size' => $this->file_size];
        }

        return $data;
    }
}
