<?php

namespace App\Http\Resources;

use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VoucherResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $used = (bool) ($this->resource->getAttributes()['has_redemptions'] ?? false);
        // What the voucher is doing right now; `status` stays the stored ACTIVE/INACTIVE switch.
        $state = match (true) {
            $this->status !== Voucher::ACTIVE => 'INACTIVE',
            now()->lt($this->starts_at) => 'SCHEDULED',
            now()->gt($this->ends_at) => 'EXPIRED',
            $this->usage_limit !== null && $this->used_count >= $this->usage_limit => 'EXHAUSTED',
            default => 'ACTIVE',
        };

        return [
            'id' => (string) $this->id, 'code' => $this->code, 'scope' => $this->scope,
            'shop' => $this->shop ? ['id' => (string) $this->shop->id, 'name' => $this->shop->name, 'slug' => $this->shop->slug] : null,
            'type' => $this->type, 'value' => $this->value, 'min_order' => $this->min_order, 'max_discount' => $this->max_discount,
            'usage_limit' => $this->usage_limit, 'per_user_limit' => $this->per_user_limit, 'used_count' => $this->used_count,
            'starts_at' => $this->starts_at->toISOString(), 'ends_at' => $this->ends_at->toISOString(),
            'status' => $this->status, 'state' => $state, 'has_redemptions' => $used,
            'can_manage' => $request->user()?->can('update', $this->resource) ?? false,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
