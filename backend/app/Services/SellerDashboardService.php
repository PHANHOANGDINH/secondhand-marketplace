<?php

namespace App\Services;

use App\Enums\ProductStatus;
use App\Enums\ShopOrderStatus;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SellerDashboardService
{
    private function ownShop(User $user): Shop
    {
        $shop = $user->shop()->firstOrFail();
        Gate::forUser($user)->authorize('view', $shop);

        return $shop;
    }

    private function within(Builder $query, string $column, SellerReportRange $range): Builder
    {
        return $query->where($column, '>=', $range->startUtc())->where($column, '<', $range->endUtc());
    }

    private function completed(Shop $shop, SellerReportRange $range): Builder
    {
        return $this->within(ShopOrder::where('shop_id', $shop->id)->where('status', ShopOrderStatus::COMPLETED), 'completed_at', $range);
    }

    /** Only grouped counts, never full product/order records. */
    private function counts(Builder $query, array $statuses): array
    {
        $rows = $query->toBase()->select('status')->selectRaw('COUNT(*) AS aggregate')->groupBy('status')->get()->keyBy('status');
        $result = [];
        foreach ($statuses as $status) {
            $result[$status->value] = (int) ($rows->get($status->value)?->aggregate ?? 0);
        }

        return $result;
    }

    private function totals(Shop $shop, SellerReportRange $range): array
    {
        $totals = $this->completed($shop, $range)->toBase()->selectRaw('COALESCE(SUM(subtotal), 0) AS revenue, COUNT(*) AS completed_orders')->first();

        return ['total_revenue' => bcadd((string) $totals->revenue, '0', 2), 'completed_orders' => (int) $totals->completed_orders];
    }

    public function dashboard(User $user, array $filters): array
    {
        return DB::transaction(function () use ($user, $filters): array {
            $shop = $this->ownShop($user);
            $range = SellerReportRange::fromFilters($filters);
            $products = $this->counts(Product::where('shop_id', $shop->id), ProductStatus::cases());
            $orders = $this->counts($this->within(ShopOrder::where('shop_id', $shop->id), 'created_at', $range), ShopOrderStatus::cases());
            $totals = $this->totals($shop, $range);
            $recent = $this->within(ShopOrder::where('shop_id', $shop->id), 'created_at', $range)
                ->select(['id', 'order_id', 'total_amount', 'status', 'created_at'])
                ->with(['order:id,order_code,buyer_id', 'order.buyer:id,name,deleted_at'])
                ->orderByDesc('created_at')->orderByDesc('id')->limit(5)->get()->map(fn (ShopOrder $part): array => [
                    'id' => (string) $part->id, 'code' => $part->order->order_code,
                    'buyer' => ['name' => $part->order->buyer && ! $part->order->buyer->trashed() ? $part->order->buyer->name : 'Người mua'],
                    'total_amount' => $part->total_amount, 'status' => $part->status->value, 'created_at' => $part->created_at->toISOString(),
                ])->all();

            return ['shop' => ['id' => (string) $shop->id, 'name' => $shop->name, 'status' => $shop->status->value], 'range' => $range->toArray(),
                'total_products' => array_sum($products), 'active_products' => $products['ACTIVE'], 'pending_products' => $products['PENDING'], 'sold_products' => $products['SOLD'], 'product_counts' => $products,
                'total_orders' => array_sum($orders), 'pending_orders' => $orders['PENDING'], 'completed_orders' => $orders['COMPLETED'], 'cancelled_orders' => $orders['CANCELLED'], 'order_counts' => $orders,
                'total_revenue' => $totals['total_revenue'], 'completed_in_range' => $totals['completed_orders'], 'recent_orders' => $recent];
        });
    }

    public function revenue(User $user, array $filters): array
    {
        return DB::transaction(function () use ($user, $filters): array {
            $shop = $this->ownShop($user);
            $range = SellerReportRange::fromFilters($filters);
            $group = $filters['group_by'] ?? 'day';
            $format = $group === 'month' ? '%Y-%m' : '%Y-%m-%d';
            // Reporting dates >=2000 use Vietnam's UTC+07; no MySQL timezone-table dependency.
            $rows = $this->completed($shop, $range)->toBase()
                ->selectRaw('DATE_FORMAT(DATE_ADD(completed_at, INTERVAL 7 HOUR), ?) AS period', [$format])
                ->selectRaw('SUM(subtotal) AS revenue, COUNT(*) AS completed_orders')->groupBy('period')->orderBy('period')->get()->keyBy('period');
            $totals = $this->totals($shop, $range);
            $series = [];
            $cursor = $group === 'month' ? $range->from->startOfMonth() : $range->from;
            while ($cursor <= $range->to) {
                $period = $cursor->format($group === 'month' ? 'Y-m' : 'Y-m-d');
                $row = $rows->get($period);
                $series[] = ['period' => $period, 'revenue' => bcadd((string) ($row?->revenue ?? '0'), '0', 2), 'completed_orders' => (int) ($row?->completed_orders ?? 0)];
                $cursor = $group === 'month' ? $cursor->addMonth() : $cursor->addDay();
            }

            return ['range' => $range->toArray(), 'group_by' => $group, ...$totals, 'series' => $series];
        });
    }
}
