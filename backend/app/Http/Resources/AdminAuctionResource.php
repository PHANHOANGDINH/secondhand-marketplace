<?php

namespace App\Http\Resources;

use App\Enums\AuctionStatus;
use Illuminate\Http\Request;

class AdminAuctionResource extends AuctionResource
{
    public function toArray(Request $request): array
    {
        $open = in_array($this->status, [AuctionStatus::SCHEDULED, AuctionStatus::LIVE], true);

        return [...parent::toArray($request),
            'seller' => ['id' => (string) $this->seller_id, 'name' => $this->seller && ! $this->seller->trashed() ? $this->seller->name : 'Người bán'],
            'overdue' => $open && $this->ends_at->lessThan(now()->subMinutes(config('admin-action-center.overdue_grace_minutes'))),
            'can_cancel' => $open && $request->user()->can('cancelAsAdmin', $this->resource),
        ];
    }
}
