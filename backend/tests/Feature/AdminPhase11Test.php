<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Review;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\User;
use App\Services\AdminUserService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminPhase11Test extends TestCase
{
    use RefreshDatabase;

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

    private function admin(): User
    {
        $user = User::factory()->create(['role' => 'ADMIN']);
        $this->login($user);

        return $user;
    }

    private function transition(string $from, string $to): array
    {
        return ['expected_status' => $from, 'status' => $to, 'reason' => 'Policy violation reviewed'];
    }

    private function order(array $statuses, ?User $buyer = null, ?Shop $shop = null, array $data = []): Order
    {
        $order = Order::factory()->create(['buyer_id' => ($buyer ?? User::factory()->create())->id, ...$data]);
        foreach ($statuses as $status) {
            ShopOrder::factory()->create(['order_id' => $order->id, 'shop_id' => ($shop ?? Shop::factory()->create())->id, 'status' => $status, 'subtotal' => '0.10', 'completed_at' => $status === 'COMPLETED' ? now() : null]);
        }

        return $order;
    }

    public function test_all_admin_endpoints_require_active_admin_authorization(): void
    {
        $target = User::factory()->create();
        $review = Review::factory()->create();
        $order = $review->orderItem->shopOrder->order;
        $paths = ['/admin/dashboard', '/admin/users', '/admin/users/'.$target->id, '/admin/reviews', '/admin/reviews/'.$review->id, '/admin/orders', '/admin/orders/'.$order->id];
        foreach ($paths as $path) {
            $this->getJson('/api/v1'.$path)->assertUnauthorized();
        }
        $this->patchJson('/api/v1/admin/users/'.$target->id.'/status', $this->transition('ACTIVE', 'SUSPENDED'))->assertUnauthorized();
        $this->patchJson('/api/v1/admin/reviews/'.$review->id.'/status', $this->transition('PUBLISHED', 'HIDDEN'))->assertUnauthorized();
        $this->login($target);
        foreach ($paths as $path) {
            $this->getJson('/api/v1'.$path)->assertForbidden();
        }
        $this->patchJson('/api/v1/admin/users/'.$target->id.'/status', $this->transition('ACTIVE', 'SUSPENDED'))->assertForbidden();
        $this->patchJson('/api/v1/admin/reviews/'.$review->id.'/status', $this->transition('PUBLISHED', 'HIDDEN'))->assertForbidden();
        $admin = $this->admin();
        $admin->forceFill(['status' => 'SUSPENDED'])->save();
        $this->getJson('/api/v1/admin/dashboard')->assertForbidden();
    }

    public function test_dashboard_counts_current_inventory_and_order_aggregates_without_double_counting(): void
    {
        $this->admin();
        $a = User::factory()->create();
        $b = User::factory()->create();
        $buyer = User::factory()->create();
        $suspended = User::factory()->create(['status' => 'SUSPENDED']);
        User::factory()->create()->delete();
        $sa = Shop::factory()->create(['owner_id' => $a->id, 'status' => 'ACTIVE']);
        $sb = Shop::factory()->create(['owner_id' => $b->id, 'status' => 'PENDING']);
        Shop::factory()->create(['owner_id' => $suspended->id])->delete();
        foreach (['ACTIVE', 'PENDING', 'SOLD'] as $status) {
            Product::factory()->for($sa)->create(['status' => $status]);
        }
        Product::factory()->for($sb)->active()->create();
        Product::factory()->for($sa)->create()->delete();
        $done = $this->order(['COMPLETED'], $buyer, $sa);
        $this->order(['CANCELLED'], $buyer, $sa);
        $this->order(['PENDING'], $buyer, $sa);
        $mixed = $this->order(['COMPLETED'], $buyer, $sa);
        ShopOrder::factory()->create(['order_id' => $mixed->id, 'shop_id' => $sb->id, 'status' => 'CANCELLED', 'subtotal' => '999.99']);
        ShopOrder::factory()->create(['order_id' => $done->id, 'shop_id' => $sb->id, 'status' => 'COMPLETED', 'subtotal' => '999999.99', 'shipping_fee' => '55.00']);
        Payment::factory()->for($done)->create(['attempt_number' => 1, 'status' => 'CANCELLED']);
        Payment::factory()->for($done)->paid()->create(['attempt_number' => 2]);
        $this->getJson('/api/v1/admin/dashboard')->assertOk()->assertJsonPath('data.total_users', 5)->assertJsonPath('data.active_users', 4)->assertJsonPath('data.total_shops', 2)->assertJsonPath('data.active_shops', 1)->assertJsonPath('data.pending_shops', 1)->assertJsonPath('data.total_products', 4)->assertJsonPath('data.active_products', 2)->assertJsonPath('data.pending_products', 1)->assertJsonPath('data.total_orders', 4)->assertJsonPath('data.completed_orders', 1)->assertJsonPath('data.cancelled_orders', 1)->assertJsonPath('data.total_revenue', '1000000.19');
    }

    public function test_dashboard_empty_values_and_large_decimal_sum_are_exact(): void
    {
        $this->admin();
        $this->getJson('/api/v1/admin/dashboard')->assertOk()->assertJsonPath('data.total_orders', 0)->assertJsonPath('data.total_revenue', '0.00')->assertJsonPath('data.recent_orders', []);
        foreach (['9999999999999.99', '9999999999999.99', '0.10'] as $value) {
            ShopOrder::factory()->completed()->create(['subtotal' => $value]);
        }
        foreach (['PENDING', 'CONFIRMED', 'PROCESSING', 'SHIPPING', 'CANCELLED'] as $status) {
            ShopOrder::factory()->create(['status' => $status, 'subtotal' => '999999.99']);
        }
        $this->getJson('/api/v1/admin/dashboard')->assertJsonPath('data.total_revenue', '20000000000000.08');
    }

    public function test_recent_records_are_limited_sorted_and_redacted(): void
    {
        $this->admin();
        $users = User::factory()->count(7)->create();
        $orders = [];
        foreach ($users as $u) {
            $orders[] = $this->order(['PENDING'], $u);
        }
        $response = $this->getJson('/api/v1/admin/dashboard')->assertOk()->assertJsonCount(5, 'data.recent_orders')->assertJsonCount(5, 'data.recent_users')->assertJsonPath('data.recent_orders.0.id', (string) $orders[6]->id)->assertJsonMissingPath('data.recent_orders.0.payments')->assertJsonMissingPath('data.recent_orders.0.shipping_address')->assertJsonMissingPath('data.recent_users.0.email')->assertJsonMissingPath('data.recent_users.0.password');
        $this->assertGreaterThan((int) $response->json('data.recent_users.1.id'), (int) $response->json('data.recent_users.0.id'));
    }

    public function test_user_search_status_role_pagination_and_archived_read_only(): void
    {
        $admin = $this->admin();
        $a = User::factory()->create(['name' => 'Needle person', 'email' => 'needle@example.test']);
        $b = User::factory()->create(['name' => 'Other', 'email' => 'needle-b@example.test', 'status' => 'SUSPENDED']);
        $gone = User::factory()->create(['name' => 'Needle archived']);
        $gone->delete();
        $this->getJson('/api/v1/admin/users?search=needle&role=USER&status=ACTIVE&per_page=1')->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.id', (string) $gone->id)->assertJsonPath('data.0.archived', true)->assertJsonPath('data.0.can_moderate', false);
        $this->getJson('/api/v1/admin/users?search=needle&role=USER&status=ACTIVE&per_page=1&page=2')->assertJsonPath('data.0.id', (string) $a->id);
        $this->getJson('/api/v1/admin/users?search=needle&status=SUSPENDED')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', (string) $b->id);
        $this->getJson('/api/v1/admin/users?search=%25')->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/admin/users/'.$admin->id)->assertJsonPath('data.can_moderate', false)->assertJsonMissingPath('data.password')->assertJsonMissingPath('data.remember_token');
        $this->getJson('/api/v1/admin/users/'.$gone->id)->assertOk();
        $this->patchJson('/api/v1/admin/users/'.$gone->id.'/status', $this->transition('ACTIVE', 'SUSPENDED'))->assertForbidden();
    }

    public function test_suspend_restore_user_only_changes_status_and_audits_both_actions(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();
        $before = $target->getAttributes();
        $url = '/api/v1/admin/users/'.$target->id.'/status';
        $this->patchJson($url, $this->transition('ACTIVE', 'SUSPENDED'))->assertOk()->assertJsonPath('data.status', 'SUSPENDED');
        $target->refresh();
        foreach (['name', 'email', 'password', 'role', 'deleted_at'] as $key) {
            $this->assertSame($before[$key] ?? null, $target->getAttributes()[$key] ?? null);
        }
        $this->patchJson($url, $this->transition('SUSPENDED', 'ACTIVE'))->assertOk();
        $this->assertDatabaseCount('audit_logs', 2);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $admin->id, 'entity_type' => 'users', 'entity_id' => $target->id, 'from_status' => 'ACTIVE', 'to_status' => 'SUSPENDED', 'reason' => 'Policy violation reviewed']);
    }

    public function test_status_transitions_stale_self_and_field_injection_are_rejected(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();
        $url = '/api/v1/admin/users/'.$target->id.'/status';
        $this->patchJson('/api/v1/admin/users/'.$admin->id.'/status', $this->transition('ACTIVE', 'SUSPENDED'))->assertForbidden();
        $this->patchJson($url, $this->transition('ACTIVE', 'ACTIVE'))->assertConflict();
        $this->patchJson($url, $this->transition('SUSPENDED', 'ACTIVE'))->assertConflict();
        foreach (['role' => 'ADMIN', 'email' => 'changed@example.test', 'password' => 'secret', 'deleted_at' => '2026-01-01', 'user_id' => 1] as $field => $value) {
            $this->patchJson($url, [...$this->transition('ACTIVE', 'SUSPENDED'), $field => $value])->assertUnprocessable();
        }
        $this->patchJson($url, $this->transition('ACTIVE', 'DISABLED'))->assertUnprocessable();
        $this->patchJson($url, ['status' => 'SUSPENDED'])->assertUnprocessable();
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_suspended_admin_cannot_use_stale_identity_to_suspend_remaining_admin(): void
    {
        $first = $this->admin();
        $second = User::factory()->create(['role' => 'ADMIN']);
        $this->patchJson('/api/v1/admin/users/'.$second->id.'/status', $this->transition('ACTIVE', 'SUSPENDED'))->assertOk();
        $this->expectException(AuthorizationException::class);
        app(AdminUserService::class)->moderate($second, $first, $this->transition('ACTIVE', 'SUSPENDED'));
    }

    public function test_suspension_blocks_existing_session_and_new_login_but_keeps_history(): void
    {
        $admin = $this->admin();
        $review = Review::factory()->create();
        $buyer = $review->user;
        $order = $review->orderItem->shopOrder->order;
        $buyer->password = 'Known-test-password-123';
        $buyer->save();
        $this->patchJson('/api/v1/admin/users/'.$buyer->id.'/status', $this->transition('ACTIVE', 'SUSPENDED'))->assertOk();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'buyer_id' => $buyer->id]);
        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'rating' => 5]);
        $this->login($buyer->fresh());
        $this->getJson('/api/v1/auth/me')->assertForbidden();
        $this->getJson('/api/v1/orders/'.$order->id)->assertForbidden();
        $this->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->postJson('/api/v1/auth/login', ['email' => $buyer->email, 'password' => 'Known-test-password-123'])->assertUnprocessable();
        $this->login($admin);
        $this->patchJson('/api/v1/admin/users/'.$buyer->id.'/status', $this->transition('SUSPENDED', 'ACTIVE'))->assertOk();
        $this->login($buyer->fresh());
        $this->getJson('/api/v1/orders/'.$order->id)->assertOk();
    }

    public function test_audit_failure_rolls_back_user_and_review_status(): void
    {
        $this->admin();
        $user = User::factory()->create();
        $review = Review::factory()->create();
        AuditLog::creating(function (): void {
            throw new \RuntimeException('Audit failure');
        });
        try {
            $this->patchJson('/api/v1/admin/users/'.$user->id.'/status', $this->transition('ACTIVE', 'SUSPENDED'))->assertStatus(500);
            $this->patchJson('/api/v1/admin/reviews/'.$review->id.'/status', $this->transition('PUBLISHED', 'HIDDEN'))->assertStatus(500);
        } finally {
            AuditLog::flushEventListeners();
        }
        $this->assertSame(UserStatus::ACTIVE, $user->fresh()->status);
        $this->assertSame('PUBLISHED', $review->fresh()->status->value);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_review_hide_unhide_changes_public_list_and_rating_summary_without_editing_content(): void
    {
        $this->admin();
        $review = Review::factory()->create(['rating' => 4, 'comment' => 'Original review']);
        $product = $review->product;
        $url = '/api/v1/admin/reviews/'.$review->id.'/status';
        $public = '/api/v1/products/'.$product->slug;
        $this->getJson($public)->assertOk()->assertJsonPath('data.review_count', 1)->assertJsonPath('data.average_rating', 4);
        $this->patchJson($url, $this->transition('PUBLISHED', 'HIDDEN'))->assertOk()->assertJsonPath('data.comment', 'Original review')->assertJsonPath('data.rating', 4);
        $this->getJson($public)->assertJsonPath('data.review_count', 0)->assertJsonPath('data.average_rating', null);
        $this->getJson($public.'/reviews')->assertJsonPath('meta.total', 0);
        $this->patchJson($url, $this->transition('HIDDEN', 'PUBLISHED'))->assertOk();
        $this->getJson($public)->assertJsonPath('data.review_count', 1)->assertJsonPath('data.average_rating', 4);
        $this->getJson($public.'/reviews')->assertJsonPath('meta.total', 1)->assertJsonMissingPath('data.0.moderation_reason');
        $this->assertDatabaseCount('audit_logs', 2);
        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'reviews', 'entity_id' => $review->id, 'from_status' => 'PUBLISHED', 'to_status' => 'HIDDEN']);
    }

    public function test_review_list_filters_paginates_and_keeps_archived_product_history(): void
    {
        $this->admin();
        $review = Review::factory()->create(['rating' => 2, 'status' => 'HIDDEN']);
        Review::factory()->create();
        $review->product->delete();
        $review->user->delete();
        $this->getJson('/api/v1/admin/reviews?status=HIDDEN&rating=2&product='.$review->product_id.'&per_page=1')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', (string) $review->id)->assertJsonPath('data.0.product.archived', true)->assertJsonPath('data.0.author.name', 'Người mua');
        $this->getJson('/api/v1/admin/reviews/'.$review->id)->assertOk()->assertJsonPath('data.rating', 2)->assertJsonMissingPath('data.author.email');
    }

    public function test_review_stale_invalid_and_content_injection_are_rejected(): void
    {
        $this->admin();
        $review = Review::factory()->create();
        $url = '/api/v1/admin/reviews/'.$review->id.'/status';
        $this->patchJson($url, $this->transition('PUBLISHED', 'PUBLISHED'))->assertConflict();
        $this->patchJson($url, $this->transition('HIDDEN', 'PUBLISHED'))->assertConflict();
        foreach (['rating' => 1, 'comment' => 'Changed', 'user_id' => 1, 'product_id' => 1] as $field => $value) {
            $this->patchJson($url, [...$this->transition('PUBLISHED', 'HIDDEN'), $field => $value])->assertUnprocessable();
        }
        $this->patchJson($url, ['expected_status' => 'PUBLISHED', 'status' => 'HIDDEN', 'reason' => ' '])->assertUnprocessable();
        $this->patchJson($url, $this->transition('PUBLISHED', 'DELETED'))->assertUnprocessable();
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_order_overview_filters_overall_and_shop_status_without_duplicate_orders(): void
    {
        $this->admin();
        $done = $this->order(['COMPLETED', 'COMPLETED']);
        $partial = $this->order(['COMPLETED', 'CANCELLED']);
        $progress = $this->order(['COMPLETED', 'SHIPPING']);
        $cancelled = $this->order(['CANCELLED', 'CANCELLED']);
        $pending = $this->order(['PENDING', 'PENDING']);
        foreach (['COMPLETED' => $done, 'PARTIALLY_COMPLETED' => $partial, 'IN_PROGRESS' => $progress, 'CANCELLED' => $cancelled, 'PENDING' => $pending] as $status => $order) {
            $this->getJson('/api/v1/admin/orders?overall_status='.$status)->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', (string) $order->id)->assertJsonPath('data.0.overall_status', $status);
        }
        $this->getJson('/api/v1/admin/orders?shop_order_status=COMPLETED&per_page=1')->assertJsonPath('meta.total', 3)->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/admin/orders?buyer='.$done->buyer_id)->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', (string) $done->id);
    }

    public function test_order_date_range_uses_vietnam_creation_dates_and_pagination(): void
    {
        $this->admin();
        $buyer = User::factory()->create();
        $a = $this->order(['PENDING'], $buyer, null, ['created_at' => '2026-08-31 17:00:00']);
        $b = $this->order(['PENDING'], $buyer, null, ['created_at' => '2026-09-01 16:59:59.999999']);
        $this->order(['PENDING'], $buyer, null, ['created_at' => '2026-09-01 17:00:00']);
        $this->getJson('/api/v1/admin/orders?from=2026-09-01&to=2026-09-01&per_page=1')->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.id', (string) $b->id);
        $this->getJson('/api/v1/admin/orders?from=2026-09-01&to=2026-09-01&per_page=1&page=2')->assertJsonPath('data.0.id', (string) $a->id);
    }

    public function test_admin_order_detail_is_read_only_and_keeps_snapshots_after_soft_deletes(): void
    {
        $this->admin();
        $item = OrderItem::factory()->create(['product_name' => 'Historical name', 'product_price' => '0.10', 'subtotal' => '0.20', 'quantity' => 2]);
        $order = $item->shopOrder->order;
        $item->product->delete();
        $item->shopOrder->shop->delete();
        $order->buyer->delete();
        $url = '/api/v1/admin/orders/'.$order->id;
        $this->getJson($url)->assertOk()->assertJsonPath('data.buyer.name', 'Người mua')->assertJsonPath('data.shop_orders.0.items.0.product_name', 'Historical name')->assertJsonPath('data.shop_orders.0.items.0.product_price', '0.10')->assertJsonPath('data.shop_orders.0.shop.archived', true)->assertJsonMissingPath('data.shop_orders.0.items.0.can_review')->assertJsonMissingPath('data.payments')->assertJsonMissingPath('data.shipping_address')->assertJsonMissingPath('data.buyer.email');
        $this->patchJson($url, ['status' => 'COMPLETED'])->assertStatus(405);
        $this->deleteJson($url)->assertStatus(405);
        $this->patchJson('/api/v1/seller/orders/'.$item->shop_order_id.'/status', ['expected_status' => 'PENDING', 'status' => 'CONFIRMED'])->assertForbidden();
    }

    public function test_admin_list_validation_and_unknown_fields_are_strict(): void
    {
        $this->admin();
        foreach (['/admin/users?per_page=101', '/admin/users?status=DISABLED', '/admin/users?role=SELLER', '/admin/users?password=x', '/admin/reviews?rating=6', '/admin/reviews?status=DRAFT', '/admin/orders?overall_status=SHIPPING', '/admin/orders?shop_order_status=BAD', '/admin/orders?buyer=0', '/admin/orders?from=2026-09-31&to=2026-10-01', '/admin/orders?from=2026-09-01', '/admin/orders?from=2026-09-02&to=2026-09-01', '/admin/orders?from=2024-01-01&to=2025-01-01', '/admin/dashboard?shop_id=1'] as $path) {
            $this->getJson('/api/v1'.$path)->assertUnprocessable();
        }
    }

    public function test_admin_queries_are_bounded_eager_and_do_not_load_payments_for_statistics(): void
    {
        $this->admin();
        $this->order(['COMPLETED']);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson('/api/v1/admin/dashboard')->assertOk();
        $small = count(DB::getQueryLog());
        for ($i = 0; $i < 8; $i++) {
            $this->order(['COMPLETED', 'CANCELLED']);
        }
        DB::flushQueryLog();
        $this->getJson('/api/v1/admin/dashboard')->assertOk();
        $queries = DB::getQueryLog();
        $this->assertLessThanOrEqual($small + 1, count($queries));
        $sql = strtolower(implode(' ', array_column($queries, 'query')));
        $this->assertStringContainsString('sum(', $sql);
        $this->assertStringNotContainsString('`payments`', $sql);
        DB::flushQueryLog();
        $this->getJson('/api/v1/admin/orders?per_page=1')->assertOk();
        $one = count(DB::getQueryLog());
        DB::flushQueryLog();
        $this->getJson('/api/v1/admin/orders?per_page=20')->assertOk();
        $this->assertLessThanOrEqual($one + 1, count(DB::getQueryLog()));
        DB::disableQueryLog();
    }
}
