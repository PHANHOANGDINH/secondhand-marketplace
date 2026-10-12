<?php

namespace App\Services;

use App\Enums\ShopStatus;
use App\Models\Auction;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class SellerInventoryService
{
    public function shop(User $user): Shop
    {
        $shop = $user->shop()->firstOrFail();
        Gate::forUser($user)->authorize('view', $shop);

        return $shop;
    }

    public function inventory(Shop $shop, array $filters): Builder
    {
        $query = Product::where('shop_id', $shop->id);
        $filter = $filters['filter'] ?? 'all';
        if (in_array($filter, ['low_stock', 'out_of_stock'], true)) {
            $query->whereIn('status', ['ACTIVE', 'SOLD']);
            if ($filter === 'low_stock') {
                $query->where('stock_quantity', '>', 0)->where('stock_quantity', '<=', config('seller-inventory.low_stock_threshold'));
            } else {
                $query->where('stock_quantity', '<=', 0);
            }
        } elseif ($filter !== 'all') {
            $query->where('status', strtoupper($filter));
        }
        if (($filters['search'] ?? '') !== '') {
            $search = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($filters['search']));
            $query->whereRaw("name LIKE ? ESCAPE '!'", ['%'.$search.'%']);
        }
        $sort = $filters['sort'] ?? 'updated';

        return match ($sort) {
            'stock_asc' => $query->orderBy('stock_quantity')->orderByDesc('id'),
            'stock_desc' => $query->orderByDesc('stock_quantity')->orderByDesc('id'),
            'newest' => $query->orderByDesc('created_at')->orderByDesc('id'),
            default => $query->orderByDesc('updated_at')->orderByDesc('id'),
        };
    }

    public function actionableOrders(Builder $query): Builder
    {
        return $query->whereIn('status', ['PENDING', 'CONFIRMED', 'PROCESSING', 'SHIPPING'])
            ->whereHas('shop', fn (Builder $q): Builder => $q->where('status', 'ACTIVE'))
            ->whereHas('order', fn (Builder $q): Builder => $q->where('payment_method', 'COD')->orWhere(fn (Builder $online): Builder => $online->where('payment_method', 'ZALOPAY')
                ->whereHas('payments', fn (Builder $p): Builder => $p->where('status', 'PAID'))
                ->whereDoesntHave('payments', fn (Builder $p): Builder => $p->where('reconciliation_required', true)->whereNull('reconciliation_resolved_at'))));
    }

    public function liveAuctions(Builder $query, bool $endingSoon = false): Builder
    {
        $time = now();
        $query->whereHas('product', fn (Builder $q): Builder => $q->whereNull('products.deleted_at'))->where('status', 'LIVE')->where('starts_at', '<=', $time)->where('ends_at', '>', $time);
        if ($endingSoon) {
            $query->where('ends_at', '<=', $time->copy()->addMinutes(config('seller-inventory.ending_soon_minutes')));
        }

        return $query;
    }

    public function summary(Shop $shop): array
    {
        $threshold = config('seller-inventory.low_stock_threshold');
        $counts = Product::where('shop_id', $shop->id)->selectRaw("COALESCE(SUM(status IN ('ACTIVE','SOLD') AND stock_quantity > 0 AND stock_quantity <= ?),0) AS low_stock_products, COALESCE(SUM(status IN ('ACTIVE','SOLD') AND stock_quantity <= 0),0) AS out_of_stock_products, COALESCE(SUM(status = 'PENDING'),0) AS pending_products, COALESCE(SUM(status = 'REJECTED'),0) AS rejected_products", [$threshold])->first();
        $time = now();
        $auctions = $this->liveAuctions(Auction::where('seller_id', $shop->owner_id)->whereHas('product', fn (Builder $q): Builder => $q->where('shop_id', $shop->id)))
            ->selectRaw('COUNT(*) AS live_auctions, COALESCE(SUM(ends_at <= ?),0) AS auctions_ending_soon', [$time->copy()->addMinutes(config('seller-inventory.ending_soon_minutes'))])->first();

        return ['shop' => ['id' => (string) $shop->id, 'name' => $shop->name, 'status' => $shop->status->value],
            'low_stock_threshold' => $threshold, 'ending_soon_minutes' => config('seller-inventory.ending_soon_minutes'), 'server_time' => $time->toISOString(),
            'low_stock_products' => $shop->status === ShopStatus::ACTIVE ? (int) $counts->low_stock_products : 0,
            'out_of_stock_products' => $shop->status === ShopStatus::ACTIVE ? (int) $counts->out_of_stock_products : 0,
            'pending_products' => (int) $counts->pending_products, 'rejected_products' => (int) $counts->rejected_products,
            'orders_requiring_action' => $this->actionableOrders(ShopOrder::where('shop_id', $shop->id))->count(),
            'live_auctions' => (int) $auctions->live_auctions, 'auctions_ending_soon' => (int) $auctions->auctions_ending_soon];
    }
}
