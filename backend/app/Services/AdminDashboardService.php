<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdminDashboardService
{
    public function summary(): array
    {
        return DB::transaction(function (): array {
            $users = User::toBase()->selectRaw("COUNT(*) AS total, COALESCE(SUM(status = 'ACTIVE'), 0) AS active")->first();
            $shops = Shop::toBase()->selectRaw("COUNT(*) AS total, COALESCE(SUM(status = 'ACTIVE'), 0) AS active, COALESCE(SUM(status = 'PENDING'), 0) AS pending")->first();
            $products = Product::toBase()->selectRaw("COUNT(*) AS total, COALESCE(SUM(status = 'ACTIVE'), 0) AS active, COALESCE(SUM(status = 'PENDING'), 0) AS pending")->first();
            $revenue = ShopOrder::where('status', 'COMPLETED')->sum('subtotal');

            return ['total_users' => (int) $users->total, 'active_users' => (int) $users->active, 'total_shops' => (int) $shops->total, 'active_shops' => (int) $shops->active, 'pending_shops' => (int) $shops->pending, 'total_products' => (int) $products->total, 'active_products' => (int) $products->active, 'pending_products' => (int) $products->pending,
                'total_orders' => Order::count(), 'completed_orders' => OrderSummary::filter(Order::query(), 'COMPLETED')->count(), 'cancelled_orders' => OrderSummary::filter(Order::query(), 'CANCELLED')->count(), 'total_revenue' => bcadd((string) $revenue, '0', 2),
                'recent_orders' => Order::with(AdminOrderQuery::RELATIONS)->latest('id')->limit(5)->get(),
                'recent_users' => User::select(['id', 'name', 'role', 'status', 'created_at'])->latest('id')->limit(5)->get()->map(fn (User $u): array => ['id' => (string) $u->id, 'name' => $u->name, 'role' => $u->role->value, 'status' => $u->status->value, 'created_at' => $u->created_at->toISOString()])->all()];
        });
    }
}
