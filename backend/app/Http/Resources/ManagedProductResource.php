<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class ManagedProductResource extends ProductResource
{
    public function toArray(Request $request): array
    {
        $stock = $this->stock_quantity;
        $threshold = config('seller-inventory.low_stock_threshold');
        $claim = $this->relationLoaded('auctionClaim') && $this->auctionClaim !== null;

        return ['auction_locked' => $claim, 'inventory_state' => $stock <= 0 ? 'OUT_OF_STOCK' : ($stock <= $threshold ? 'LOW_STOCK' : 'NORMAL'),
            'stock_editable' => $request->user()->can('update', $this->resource) && ! $claim,
            'stock_edit_reason' => $claim ? 'Sản phẩm đang được giữ cho đấu giá.' : ($request->user()->can('update', $this->resource) ? null : 'Gian hàng hiện không cho phép chỉnh sửa sản phẩm.'),
            ...parent::toArray($request), 'status' => $this->status->value, 'moderation_reason' => $this->moderation_reason, 'updated_at' => $this->updated_at?->toISOString(), 'archived' => $this->trashed(),
            'can_edit' => $request->user()->can('update', $this->resource), 'can_moderate' => $request->user()->can('moderate', $this->resource), 'shop_status' => $this->shop?->status->value];
    }
}
