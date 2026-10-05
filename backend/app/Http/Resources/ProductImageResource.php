<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => (string) $this->id, 'url' => $this->image_path === 'placeholders/product.svg' ? asset('placeholders/product.svg') : Storage::disk('public')->url($this->image_path), 'is_primary' => (bool) $this->is_primary, 'sort_order' => $this->sort_order];
    }
}
