<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;

class AdminOrderQuery
{
    public const RELATIONS = ['buyer:id,name,deleted_at', 'shopOrders.shop:id,name,slug,deleted_at'];

    public function apply(array $filters): Builder
    {
        $query = OrderSummary::filter(Order::query(), $filters['overall_status'] ?? null);
        if (isset($filters['shop_order_status'])) {
            $query->whereHas('shopOrders', fn (Builder $q) => $q->where('status', $filters['shop_order_status']));
        }
        if (isset($filters['buyer'])) {
            $query->where('buyer_id', $filters['buyer']);
        }
        if (isset($filters['from'])) {
            $range = SellerReportRange::fromFilters($filters);
            $query->where('created_at', '>=', $range->startUtc())->where('created_at', '<', $range->endUtc());
        }

        return $query->with(self::RELATIONS)->orderByDesc('id');
    }
}
