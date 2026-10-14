<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShopResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => (string) $this->id, 'name' => $this->name, 'slug' => $this->slug, 'description' => $this->description, 'province_code' => $this->province_code, 'province_name' => $this->province_code === null ? null : config('provinces.list.'.$this->province_code), 'logo_path' => $this->logo_path, 'status' => $this->status->value, 'created_at' => $this->created_at?->toISOString()];
    }
}
