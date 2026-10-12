<?php

namespace App\Services;

use App\Enums\AuctionStatus;
use App\Models\Auction;
use Illuminate\Database\Eloquent\Builder;

class FixedPriceEligibility
{
    public function claims(): Builder
    {
        return Auction::where(function (Builder $query): void {
            $query->whereIn('status', [AuctionStatus::SCHEDULED, AuctionStatus::LIVE])
                ->orWhere(fn (Builder $ended): Builder => $ended->where('status', AuctionStatus::ENDED)->whereNotNull('winner_user_id')->whereNull('winner_order_id')->where('winner_checkout_expires_at', '>', now()->format('Y-m-d H:i:s.u')));
        });
    }

    /** Product lock must be held before this current locking read. */
    public function assertAvailable(int $productId): void
    {
        abort_if($this->claims()->where('product_id', $productId)->lockForUpdate()->first(), 409, 'Sản phẩm đang được giữ cho đấu giá.');
    }
}
