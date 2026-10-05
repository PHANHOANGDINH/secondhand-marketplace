<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\ShopOrderStatus;
use App\Enums\ShopStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SellerDashboardTest extends TestCase
{
    use RefreshDatabase;

    private const RANGE = '?from=2026-09-01&to=2026-09-30';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeaders(['Origin' => 'http://localhost:5173', 'Accept' => 'application/json']);
    }

    private function login(User $user): void
    {
        $this->app['session']->flush();
        Auth::forgetGuards();
        $this->actingAs($user, 'web');
    }

    private function shop(): Shop
    {
        $shop = Shop::factory()->create();
        $this->login($shop->owner);

        return $shop;
    }

    private function part(Shop $shop, array $data = []): ShopOrder
    {
        return ShopOrder::factory()->for($shop)->create(['created_at' => '2026-09-10 00:00:00', 'subtotal' => '100.00', ...$data]);
    }

    public function test_guest_and_user_without_shop_cannot_get_seller_reports(): void
    {
        foreach (['/api/v1/seller/dashboard', '/api/v1/seller/dashboard/revenue'] as $url) {
            $this->getJson($url)->assertUnauthorized();
        }
        $this->login(User::factory()->create());
        foreach (['/api/v1/seller/dashboard', '/api/v1/seller/dashboard/revenue'] as $url) {
            $this->getJson($url)->assertNotFound();
        }
    }

    public function test_product_inventory_counts_all_statuses_exclude_soft_deleted_and_other_shop(): void
    {
        $shop = $this->shop();
        foreach (ProductStatus::cases() as $status) {
            Product::factory()->for($shop)->create(['status' => $status]);
        }
        $deleted = Product::factory()->active()->for($shop)->create();
        $deleted->delete();
        Product::factory()->active()->count(2)->create();
        $this->getJson('/api/v1/seller/dashboard'.self::RANGE)->assertOk()->assertJsonPath('data.total_products', 6)->assertJsonPath('data.active_products', 1)->assertJsonPath('data.pending_products', 1)->assertJsonPath('data.sold_products', 1)->assertJsonPath('data.product_counts.HIDDEN', 1)->assertJsonPath('data.product_counts.DRAFT', 1)->assertJsonPath('data.product_counts.REJECTED', 1);
    }

    public function test_order_counts_use_creation_range_but_revenue_only_completed_subtotal(): void
    {
        $shop = $this->shop();
        foreach (ShopOrderStatus::cases() as $status) {
            $this->part($shop, ['status' => $status, 'subtotal' => '0.10', 'shipping_fee' => '5.00', 'discount_amount' => '0.03', 'completed_at' => $status === ShopOrderStatus::COMPLETED ? '2026-09-20 00:00:00' : null]);
        }
        $this->part($shop, ['created_at' => '2026-08-01 00:00:00']);
        $this->part(Shop::factory()->create(), ['status' => 'COMPLETED', 'completed_at' => '2026-09-20 00:00:00', 'subtotal' => '999999.99']);
        $this->getJson('/api/v1/seller/dashboard'.self::RANGE)->assertOk()->assertJsonPath('data.total_orders', 6)->assertJsonPath('data.pending_orders', 1)->assertJsonPath('data.completed_orders', 1)->assertJsonPath('data.cancelled_orders', 1)->assertJsonPath('data.order_counts.SHIPPING', 1)->assertJsonPath('data.order_counts.PROCESSING', 1)->assertJsonPath('data.order_counts.CONFIRMED', 1)->assertJsonPath('data.total_revenue', '0.10')->assertJsonPath('data.completed_in_range', 1);
    }

    public function test_multishop_order_and_payment_attempts_never_mix_or_duplicate_revenue(): void
    {
        $shop = $this->shop();
        $other = Shop::factory()->create();
        $order = Order::factory()->create(['subtotal' => '300.00']);
        $this->part($shop, ['order_id' => $order->id, 'status' => 'COMPLETED', 'completed_at' => '2026-09-20 00:00:00', 'subtotal' => '100.00']);
        $this->part($other, ['order_id' => $order->id, 'status' => 'COMPLETED', 'completed_at' => '2026-09-20 00:00:00', 'subtotal' => '200.00']);
        Payment::factory()->for($order)->create(['attempt_number' => 1, 'status' => 'CANCELLED']);
        Payment::factory()->for($order)->create(['attempt_number' => 2, 'status' => 'PAID']);
        $this->getJson('/api/v1/seller/dashboard/revenue'.self::RANGE)->assertOk()->assertJsonPath('data.total_revenue', '100.00')->assertJsonPath('data.completed_orders', 1);
        $this->login($other->owner);
        $this->getJson('/api/v1/seller/dashboard'.self::RANGE)->assertJsonPath('data.total_revenue', '200.00')->assertJsonPath('data.total_orders', 1);
    }

    public function test_decimal_sums_preserve_cents_even_above_single_row_decimal_limit(): void
    {
        $shop = $this->shop();
        foreach (['9999999999999.99', '9999999999999.99', '0.10'] as $amount) {
            $this->part($shop, ['status' => 'COMPLETED', 'subtotal' => $amount, 'completed_at' => '2026-09-20 00:00:00']);
        }
        $this->getJson('/api/v1/seller/dashboard'.self::RANGE)->assertOk()->assertJsonPath('data.total_revenue', '20000000000000.08');
        $this->getJson('/api/v1/seller/dashboard/revenue'.self::RANGE)->assertOk()->assertJsonPath('data.total_revenue', '20000000000000.08')->assertJsonPath('data.series.19.revenue', '20000000000000.08');
    }

    public function test_vietnam_completion_boundaries_are_inclusive_dates_with_exclusive_utc_end(): void
    {
        $shop = $this->shop();
        foreach (['2026-08-31 16:59:59.999999', '2026-08-31 17:00:00.000000', '2026-09-01 16:59:59.999999', '2026-09-01 17:00:00.000000', '2026-09-02 17:00:00.000000'] as $date) {
            $this->part($shop, ['status' => 'COMPLETED', 'subtotal' => '0.10', 'completed_at' => $date]);
        }
        $this->getJson('/api/v1/seller/dashboard/revenue?from=2026-09-01&to=2026-09-02')->assertOk()->assertJsonPath('data.range.timezone', 'Asia/Ho_Chi_Minh')->assertJsonPath('data.total_revenue', '0.30')->assertJsonCount(2, 'data.series')->assertJsonPath('data.series.0.period', '2026-09-01')->assertJsonPath('data.series.0.revenue', '0.20')->assertJsonPath('data.series.1.revenue', '0.10');
    }

    public function test_month_grouping_filters_partial_months_and_fills_empty_buckets(): void
    {
        $shop = $this->shop();
        foreach (['2026-09-29 17:00:00', '2026-09-30 17:00:00'] as $date) {
            $this->part($shop, ['status' => 'COMPLETED', 'completed_at' => $date, 'subtotal' => '1.25']);
        }
        $this->getJson('/api/v1/seller/dashboard/revenue?from=2026-09-30&to=2026-11-01&group_by=month')->assertOk()->assertJsonPath('data.group_by', 'month')->assertJsonPath('data.total_revenue', '2.50')->assertJsonCount(3, 'data.series')->assertJsonPath('data.series.0.period', '2026-09')->assertJsonPath('data.series.0.revenue', '1.25')->assertJsonPath('data.series.1.period', '2026-10')->assertJsonPath('data.series.1.revenue', '1.25')->assertJsonPath('data.series.2.revenue', '0.00');
    }

    public function test_old_orders_completed_in_range_count_as_revenue_not_new_orders_and_missing_completion_is_excluded(): void
    {
        $shop = $this->shop();
        $this->part($shop, ['created_at' => '2026-08-01 00:00:00', 'status' => 'COMPLETED', 'completed_at' => '2026-09-20 00:00:00']);
        $this->part($shop, ['created_at' => '2026-08-01 00:00:00', 'status' => 'COMPLETED', 'completed_at' => null]);
        $this->getJson('/api/v1/seller/dashboard'.self::RANGE)->assertOk()->assertJsonPath('data.total_orders', 0)->assertJsonPath('data.completed_orders', 0)->assertJsonPath('data.completed_in_range', 1)->assertJsonPath('data.total_revenue', '100.00')->assertJsonPath('data.recent_orders', []);
    }

    public function test_date_validation_boundaries_group_allowlist_and_sensitive_fields(): void
    {
        $this->shop();
        foreach (['from=2026-09-31&to=2026-10-01', 'from=bad&to=2026-10-01', 'from=2026-09-02&to=2026-09-01', 'from=2026-09-01', 'to=2026-09-01', 'from=2024-01-01&to=2025-01-01', 'from=1999-12-31&to=2000-01-01', 'from=2026-09-01T00:00:00&to=2026-09-02', 'group_by=year', 'group_by=completed_at);DROP TABLE orders'] as $query) {
            $this->getJson('/api/v1/seller/dashboard/revenue?'.$query)->assertUnprocessable();
        }
        $this->getJson('/api/v1/seller/dashboard/revenue?from=2024-01-01&to=2024-12-31&group_by=month')->assertOk()->assertJsonCount(12, 'data.series');
        foreach (['shop_id', 'user_id', 'status', 'total_revenue', 'sort_column'] as $field) {
            $this->getJson('/api/v1/seller/dashboard?'.$field.'=1')->assertUnprocessable()->assertJsonValidationErrors($field);
            $this->getJson('/api/v1/seller/dashboard/revenue?'.$field.'=1')->assertUnprocessable();
        }
    }

    public function test_default_range_is_last_thirty_vietnam_calendar_days_and_empty_dashboard_is_zero(): void
    {
        $this->travelTo(Carbon::parse('2026-09-28 18:00:00', 'UTC'));
        $this->shop();
        $this->getJson('/api/v1/seller/dashboard')->assertOk()->assertJsonPath('data.range.from', '2026-08-31')->assertJsonPath('data.range.to', '2026-09-29')->assertJsonPath('data.total_products', 0)->assertJsonPath('data.total_orders', 0)->assertJsonPath('data.total_revenue', '0.00')->assertJsonPath('data.recent_orders', []);
        $this->getJson('/api/v1/seller/dashboard/revenue')->assertOk()->assertJsonPath('data.group_by', 'day')->assertJsonCount(30, 'data.series')->assertJsonPath('data.series.0.revenue', '0.00');
        $this->travelBack();
    }

    public function test_recent_orders_are_latest_five_of_own_shop_with_minimal_buyer_data(): void
    {
        $shop = $this->shop();
        $parts = [];
        for ($i = 1; $i <= 7; $i++) {
            $parts[] = $this->part($shop, ['created_at' => '2026-09-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT).' 00:00:00']);
        }
        $this->part(Shop::factory()->create(), ['created_at' => '2026-09-30 00:00:00']);
        $parts[6]->order->buyer->delete();
        $this->getJson('/api/v1/seller/dashboard'.self::RANGE)->assertOk()->assertJsonCount(5, 'data.recent_orders')->assertJsonPath('data.recent_orders.0.id', (string) $parts[6]->id)->assertJsonPath('data.recent_orders.4.id', (string) $parts[2]->id)->assertJsonPath('data.recent_orders.0.code', $parts[6]->order->order_code)->assertJsonPath('data.recent_orders.0.buyer.name', 'Người mua')->assertJsonMissingPath('data.recent_orders.0.buyer.email')->assertJsonMissingPath('data.recent_orders.0.buyer.id')->assertJsonMissingPath('data.recent_orders.0.shipping_address')->assertJsonMissingPath('data.recent_orders.0.payments');
    }

    public function test_non_active_shops_can_read_history_but_admin_has_no_ownership_bypass(): void
    {
        $shop = $this->shop();
        foreach ([ShopStatus::PENDING, ShopStatus::SUSPENDED, ShopStatus::REJECTED] as $status) {
            $shop->update(['status' => $status]);
            $this->getJson('/api/v1/seller/dashboard'.self::RANGE)->assertOk()->assertJsonPath('data.shop.id', (string) $shop->id);
        }
        $shop->delete();
        $this->getJson('/api/v1/seller/dashboard')->assertNotFound();
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $this->login($admin);
        $this->getJson('/api/v1/seller/dashboard')->assertNotFound();
        $this->getJson('/api/v1/seller/dashboard?shop_id='.$shop->id)->assertUnprocessable();
        $own = Shop::factory()->for($admin, 'owner')->create();
        $this->getJson('/api/v1/seller/dashboard')->assertOk()->assertJsonPath('data.shop.id', (string) $own->id);
        $admin->forceFill(['status' => UserStatus::SUSPENDED])->save();
        $this->getJson('/api/v1/seller/dashboard')->assertForbidden();
    }

    public function test_query_count_does_not_scale_with_orders_and_queries_never_read_payment_rows(): void
    {
        $shop = $this->shop();
        $this->part($shop);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson('/api/v1/seller/dashboard'.self::RANGE)->assertOk();
        $one = count(DB::getQueryLog());
        for ($i = 0; $i < 12; $i++) {
            $this->part($shop, ['status' => 'COMPLETED', 'completed_at' => '2026-09-20 00:00:00']);
        }
        DB::flushQueryLog();
        $this->getJson('/api/v1/seller/dashboard'.self::RANGE)->assertOk();
        $queries = DB::getQueryLog();
        $this->assertLessThanOrEqual($one + 1, count($queries));
        $sql = strtolower(implode(' ', array_column($queries, 'query')));
        $this->assertStringContainsString('sum(subtotal)', $sql);
        $this->assertStringNotContainsString('`payments`', $sql);
        DB::disableQueryLog();
    }
}
