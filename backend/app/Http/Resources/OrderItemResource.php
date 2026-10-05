<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['can_review' => $this->whenHas('can_review'), 'can_edit_review' => $this->whenHas('can_edit_review'), 'review' => $this->whenLoaded('review', fn () => $this->review ? new ReviewResource($this->review) : null), 'id' => (string) $this->id, 'product_id' => (string) $this->product_id, 'product_name' => $this->product_name, 'product_image_url' => $this->product_image === 'placeholders/product.svg' ? asset('placeholders/product.svg') : Storage::disk('public')->url($this->product_image), 'product_price' => $this->product_price, 'quantity' => $this->quantity, 'subtotal' => $this->subtotal];
    }
}
