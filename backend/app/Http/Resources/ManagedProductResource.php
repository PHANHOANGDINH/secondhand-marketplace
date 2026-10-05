<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class ManagedProductResource extends ProductResource
{
    public function toArray(Request $request): array
    {
        return [...parent::toArray($request), 'status' => $this->status->value, 'moderation_reason' => $this->moderation_reason, 'updated_at' => $this->updated_at?->toISOString(), 'archived' => $this->trashed(),
            'can_edit' => $request->user()->can('update', $this->resource), 'can_moderate' => $request->user()->can('moderate', $this->resource), 'shop_status' => $this->shop?->status->value];
    }
}
