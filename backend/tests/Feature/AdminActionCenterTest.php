<?php

namespace Tests\Feature;

use App\Events\AuctionStatusChanged;
use App\Events\NotificationCreated;
use App\Models\Address;
use App\Models\Auction;
use App\Models\AuditLog;
use App\Models\Bid;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Report;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\User;
use App\Models\VideoComment;
use App\Services\AuctionService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminActionCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeaders(['Origin' => 'http://localhost:5173', 'Accept' => 'application/json']);
    }

    private function login(User $user): User
    {
        $this->app['session']->flush();
        Auth::forgetGuards();
        $this->actingAs($user, 'web');

        return $user;
    }

    private function admin(): User
    {
        return $this->login(User::factory()->admin()->create());
    }

    /** Verified money on an order that could not simply accept it: the only situation that raises the flag. */
    private function flagged(string $status = 'FAILED', array $parts = ['PENDING'], array $extra = []): Payment
    {
        $order = Order::factory()->create(['payment_method' => 'ZALOPAY']);
        foreach ($parts as $part) {
            ShopOrder::factory()->for($order)->create(['status' => $part]);
        }

        return Payment::factory()->for($order)->create([
            'status' => $status, 'reconciliation_required' => true, 'gateway_transaction_id' => (string) random_int(100000, 999999999),
            'order_url' => 'https://qcgateway.zalopay.vn/openinapp?order=secret-token', 'preferred_payment_method' => ['vietqr'], 'gateway_app_id' => 2553,
            'gateway_app_time' => 1760000000000, 'request_key' => (string) Str::uuid(), 'gateway_requested_at' => now()->subHours(2), 'expires_at' => now()->subHour(), ...$extra,
        ]);
    }

    private function awaitingCheckout(?Auction $auction = null): Auction
    {
        $auction ??= Auction::factory()->create();
        $bid = Bid::factory()->for($auction)->create();
        $auction->forceFill(['status' => 'ENDED', 'winner_user_id' => $bid->user_id, 'winning_bid_id' => $bid->id, 'winner_checkout_expires_at' => now()->addHour()])->save();

        return $auction->refresh();
    }

    private function notifications(int $userId, string $type): int
    {
        return DB::table('notifications')->where('notifiable_id', $userId)->where('type', $type)->count();
    }

    private function lastAudit(string $entity, int $id): AuditLog
    {
        return AuditLog::where('entity_type', $entity)->where('entity_id', $id)->latest('id')->firstOrFail();
    }

    public function test_new_admin_endpoints_reject_guests_users_and_sellers(): void
    {
        $payment = $this->flagged();
        $auction = Auction::factory()->create();
        $requests = [
            ['getJson', '/api/v1/admin/action-center', []],
            ['getJson', '/api/v1/admin/payments?reconciliation=1', []],
            ['getJson', '/api/v1/admin/payments/'.$payment->id, []],
            ['postJson', '/api/v1/admin/payments/'.$payment->id.'/reconciliation', ['outcome' => 'REFUNDED_OUTSIDE', 'note' => 'Đã hoàn', 'expected_status' => 'FAILED']],
            ['getJson', '/api/v1/admin/auctions', []],
            ['postJson', '/api/v1/admin/auctions/'.$auction->id.'/cancel', ['reason' => 'Vi phạm', 'expected_status' => 'LIVE']],
            ['getJson', '/api/v1/admin/users?flagged=1', []],
        ];
        foreach ($requests as [$method, $url, $body]) {
            $this->{$method}($url, $body)->assertUnauthorized();
        }
        foreach ([User::factory()->create(), Shop::factory()->create()->owner, User::find($auction->seller_id), User::find($payment->order->buyer_id)] as $user) {
            $this->login($user);
            foreach ($requests as [$method, $url, $body]) {
                $this->{$method}($url, $body)->assertForbidden();
            }
        }
        $this->assertSame('FAILED', $payment->refresh()->status->value);
        $this->assertSame('LIVE', $auction->refresh()->status->value);
        $this->assertDatabaseCount('audit_logs', 0);

        $this->admin();
        foreach ($requests as [$method, $url, $body]) {
            $this->{$method}($url, $body)->assertOk();
        }
    }

    public function test_action_center_reports_every_documented_count(): void
    {
        Shop::factory()->count(2)->create(['status' => 'PENDING']);
        Shop::factory()->create(['status' => 'PENDING'])->delete();
        $owner = User::factory()->create();
        $shop = Shop::factory()->for($owner, 'owner')->create();
        $product = Product::factory()->active()->for($shop)->create();
        Product::factory()->for($shop)->create(['status' => 'PENDING']);
        Product::factory()->for($shop)->create(['status' => 'PENDING'])->delete();
        $comment = VideoComment::factory()->create(['user_id' => $owner->id]);
        // Four open reports against one owner; two against another, plus one outside the window and one closed.
        Report::factory()->create(['target_type' => 'PRODUCT', 'target_id' => $product->id]);
        Report::factory()->create(['target_type' => 'SHOP', 'target_id' => $shop->id, 'status' => 'IN_REVIEW']);
        Report::factory()->count(2)->create(['target_type' => 'VIDEO_COMMENT', 'target_id' => $comment->id]);
        Report::factory()->create(['target_type' => 'PRODUCT', 'target_id' => $product->id, 'status' => 'RESOLVED']);
        $other = Shop::factory()->create();
        Report::factory()->count(2)->create(['target_type' => 'SHOP', 'target_id' => $other->id]);
        Report::factory()->create(['target_type' => 'SHOP', 'target_id' => $other->id, 'status' => 'DISMISSED']);
        $old = Report::factory()->create(['target_type' => 'SHOP', 'target_id' => $other->id]);
        DB::table('reports')->where('id', $old->id)->update(['created_at' => now()->subDays(31)]);
        $this->flagged();
        $this->flagged('CANCELLED', ['CANCELLED']);
        $this->flagged('REFUNDED', ['CANCELLED'], ['reconciliation_resolved_at' => now()]);
        Payment::factory()->create();
        $this->awaitingCheckout();
        $this->awaitingCheckout()->forceFill(['winner_checkout_expires_at' => now()->subMinute()])->save();
        Auction::factory()->create(['starts_at' => now()->subHour(), 'ends_at' => now()->subMinutes(5)]);
        Auction::factory()->create(['status' => 'SCHEDULED', 'starts_at' => now()->subHour(), 'ends_at' => now()->subMinutes(3)]);
        Auction::factory()->create(['starts_at' => now()->subHour(), 'ends_at' => now()->subSeconds(30)]);
        Auction::factory()->create(['status' => 'CANCELLED', 'starts_at' => now()->subHour(), 'ends_at' => now()->subMinutes(5)]);

        $this->admin();
        $this->getJson('/api/v1/admin/action-center')->assertOk()->assertJsonPath('data.shops_pending', 2)->assertJsonPath('data.products_pending', 1)
            ->assertJsonPath('data.reports_open', 7)->assertJsonPath('data.reports_pending', 6)->assertJsonPath('data.reports_in_review', 1)
            ->assertJsonPath('data.reports_by_target', ['PRODUCT' => 1, 'SHOP' => 4, 'VIDEO_COMMENT' => 2])
            ->assertJsonPath('data.users_flagged', 1)->assertJsonPath('data.payments_reconciliation', 2)
            ->assertJsonPath('data.auctions_awaiting_checkout', 1)->assertJsonPath('data.auctions_overdue', 2)->assertJsonPath('data.video_comments_reported', 1)
            ->assertJsonPath('data.flagged_min_reports', 3)->assertJsonPath('data.flagged_window_days', 30)->assertJsonStructure(['data' => ['server_time']]);

        $this->getJson('/api/v1/admin/users?flagged=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', (string) $owner->id);
        $this->getJson('/api/v1/admin/users?flagged=0')->assertOk()->assertJsonPath('meta.total', User::count());
        $this->getJson('/api/v1/admin/users?flagged=maybe')->assertUnprocessable();

        config(['admin-action-center.flagged_min_reports' => 2, 'admin-action-center.flagged_window_days' => 60]);
        $this->getJson('/api/v1/admin/action-center')->assertJsonPath('data.users_flagged', 2);
        $this->getJson('/api/v1/admin/users?flagged=1')->assertJsonCount(2, 'data');
    }

    public function test_action_center_query_count_does_not_grow_with_data(): void
    {
        $this->admin();
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson('/api/v1/admin/action-center')->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };
        Report::factory()->create();
        $few = $count();
        $shop = Shop::factory()->create();
        Report::factory()->count(12)->create(['target_type' => 'SHOP', 'target_id' => $shop->id]);
        Report::factory()->count(8)->create();
        Shop::factory()->count(6)->create(['status' => 'PENDING']);
        Auction::factory()->count(6)->create(['starts_at' => now()->subHour(), 'ends_at' => now()->subMinutes(10)]);
        foreach (range(1, 4) as $i) {
            $this->flagged();
        }
        $this->assertSame($few, $count());
        $this->assertLessThanOrEqual(12, $few);
        $this->getJson('/api/v1/admin/action-center')->assertJsonPath('data.reports_open', 21)->assertJsonPath('data.auctions_overdue', 6)->assertJsonPath('data.payments_reconciliation', 4);
    }

    public function test_payment_queue_lists_only_flagged_payments_without_gateway_data(): void
    {
        $open = $this->flagged();
        $resolved = $this->flagged('REFUNDED', ['CANCELLED'], ['reconciliation_resolved_at' => now(), 'reconciliation_note' => 'Đã hoàn']);
        $plain = Payment::factory()->create();
        $this->admin();
        $this->getJson('/api/v1/admin/payments')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/admin/payments?reconciliation=resolved')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', (string) $resolved->id)->assertJsonPath('data.0.can_resolve', false);
        $response = $this->getJson('/api/v1/admin/payments?reconciliation=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', (string) $open->id)
            ->assertJsonPath('data.0.reconciliation_open', true)->assertJsonPath('data.0.can_resolve', true)->assertJsonPath('data.0.can_keep_paid', true)
            ->assertJsonPath('data.0.gateway_transaction_id', $open->gateway_transaction_id)->assertJsonPath('data.0.order.order_code', $open->order->order_code);
        $this->getJson('/api/v1/admin/payments/'.$open->id)->assertOk()->assertJsonPath('data.status', 'FAILED');
        foreach ([$response->getContent(), $this->getJson('/api/v1/admin/payments/'.$open->id)->getContent()] as $body) {
            foreach (['order_url', 'secret-token', 'qcgateway', 'mac', 'preferred_payment_method', 'vietqr', 'gateway_app_id', 'gateway_app_time', 'request_key', $open->request_key] as $secret) {
                $this->assertStringNotContainsString('"'.$secret, $body);
                $this->assertStringNotContainsString($secret.'"', $body);
            }
        }
        $this->getJson('/api/v1/admin/payments/'.$plain->id)->assertNotFound();
        $this->getJson('/api/v1/admin/payments?reconciliation=everything')->assertUnprocessable();
        $this->getJson('/api/v1/admin/payments?unknown=1')->assertUnprocessable();
    }

    public function test_reconciliation_requires_outcome_note_expected_status_and_an_open_flag(): void
    {
        $payment = $this->flagged();
        $this->admin();
        $url = '/api/v1/admin/payments/'.$payment->id.'/reconciliation';
        $this->postJson($url, [])->assertUnprocessable()->assertJsonValidationErrors(['outcome', 'note', 'expected_status']);
        $this->postJson($url, ['outcome' => 'REFUND_NOW', 'note' => '   ', 'expected_status' => 'FAILED'])->assertUnprocessable()->assertJsonValidationErrors(['outcome', 'note']);
        $this->postJson($url, ['outcome' => 'KEEP_PAID', 'note' => 'ok', 'expected_status' => 'FAILED', 'status' => 'PAID'])->assertUnprocessable();
        $this->postJson($url, ['outcome' => 'KEEP_PAID', 'note' => 'Sai phiên bản', 'expected_status' => 'PENDING'])->assertConflict();
        $plain = Payment::factory()->create();
        $this->postJson('/api/v1/admin/payments/'.$plain->id.'/reconciliation', ['outcome' => 'KEEP_PAID', 'note' => 'Không gắn cờ', 'expected_status' => 'PENDING'])->assertConflict();
        $this->assertSame('FAILED', $payment->refresh()->status->value);
        $this->assertNull($payment->reconciliation_resolved_at);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->postJson('/api/v1/admin/payments/999999/reconciliation', ['outcome' => 'KEEP_PAID', 'note' => 'x', 'expected_status' => 'PENDING'])->assertNotFound();
    }

    public function test_refunded_outside_marks_refunded_keeps_the_flag_and_cannot_be_repeated(): void
    {
        $payment = $this->flagged('CANCELLED', ['CANCELLED']);
        $admin = $this->admin();
        $url = '/api/v1/admin/payments/'.$payment->id.'/reconciliation';
        $this->getJson('/api/v1/admin/payments/'.$payment->id)->assertJsonPath('data.can_keep_paid', false);
        $this->postJson($url, ['outcome' => 'KEEP_PAID', 'note' => 'Giữ tiền', 'expected_status' => 'CANCELLED'])->assertConflict();
        $this->postJson($url, ['outcome' => 'REFUNDED_OUTSIDE', 'note' => '  Đã hoàn qua cổng đối tác ngày 12/10  ', 'expected_status' => 'CANCELLED'])->assertOk()
            ->assertJsonPath('data.status', 'REFUNDED')->assertJsonPath('data.reconciliation_required', true)->assertJsonPath('data.reconciliation_open', false)
            ->assertJsonPath('data.reconciliation_note', 'Đã hoàn qua cổng đối tác ngày 12/10')->assertJsonPath('data.reconciliation_resolved_by.id', (string) $admin->id)->assertJsonPath('data.can_resolve', false);
        $payment->refresh();
        $this->assertTrue($payment->reconciliation_required);
        $this->assertNotNull($payment->reconciliation_resolved_at);
        $this->assertNull($payment->order_url);
        $audit = $this->lastAudit('payments', $payment->id);
        $this->assertSame(['payment.reconciliation_resolved', 'CANCELLED', 'REFUNDED', $admin->id], [$audit->action, $audit->from_status, $audit->to_status, $audit->actor_id]);
        $this->assertStringStartsWith('REFUNDED_OUTSIDE: ', $audit->reason);
        $this->postJson($url, ['outcome' => 'REFUNDED_OUTSIDE', 'note' => 'Lần hai', 'expected_status' => 'REFUNDED'])->assertConflict();
        $this->assertSame(1, AuditLog::where('action', 'payment.reconciliation_resolved')->count());
    }

    public function test_keep_paid_unblocks_fulfilment_and_buyer_views(): void
    {
        $payment = $this->flagged('FAILED');
        $order = $payment->order;
        $part = $order->shopOrders()->firstOrFail();
        $seller = $part->shop->owner;
        $advance = fn () => $this->patchJson('/api/v1/seller/orders/'.$part->id.'/status', ['expected_status' => 'PENDING', 'status' => 'CONFIRMED']);

        $this->login($seller);
        $advance()->assertConflict()->assertJsonPath('message', 'Payment requires reconciliation.');
        $this->getJson('/api/v1/seller/action-center')->assertJsonPath('data.orders_requiring_action', 0);
        $this->login(User::find($order->buyer_id));
        $this->getJson('/api/v1/orders/'.$order->id)->assertOk()->assertJsonPath('data.reconciliation_required', true);

        $this->admin();
        $this->postJson('/api/v1/admin/payments/'.$payment->id.'/reconciliation', ['outcome' => 'KEEP_PAID', 'note' => 'Tiền hợp lệ, đơn còn hiệu lực', 'expected_status' => 'FAILED'])->assertOk()->assertJsonPath('data.status', 'PAID');
        $this->assertNotNull($payment->refresh()->paid_at);
        $this->assertTrue($payment->reconciliation_required);

        $this->login(User::find($order->buyer_id));
        $this->getJson('/api/v1/orders/'.$order->id)->assertJsonPath('data.reconciliation_required', false)->assertJsonPath('data.payment_status', 'PAID')
            ->assertJsonPath('data.shop_orders.0.payment_ready', true)->assertJsonPath('data.payments.0.reconciliation_required', true);
        $this->login($seller);
        $this->getJson('/api/v1/seller/action-center')->assertJsonPath('data.orders_requiring_action', 1);
        $this->getJson('/api/v1/seller/orders?action_required=1')->assertJsonCount(1, 'data');
        $advance()->assertOk()->assertJsonPath('data.status', 'CONFIRMED');
    }

    public function test_refund_leaves_fulfilment_blocked_as_unpaid_but_allows_cancellation(): void
    {
        $payment = $this->flagged('FAILED');
        $part = $payment->order->shopOrders()->firstOrFail();
        $this->admin();
        $this->postJson('/api/v1/admin/payments/'.$payment->id.'/reconciliation', ['outcome' => 'REFUNDED_OUTSIDE', 'note' => 'Đã hoàn tiền', 'expected_status' => 'FAILED'])->assertOk();
        $this->login($part->shop->owner);
        $this->patchJson('/api/v1/seller/orders/'.$part->id.'/status', ['expected_status' => 'PENDING', 'status' => 'CONFIRMED'])->assertConflict()->assertJsonPath('message', 'Online payment must be verified before fulfillment.');
        $this->patchJson('/api/v1/seller/orders/'.$part->id.'/status', ['expected_status' => 'PENDING', 'status' => 'CANCELLED', 'reason' => 'Đã hoàn tiền', 'failure_category' => 'UNFULFILLABLE'])->assertOk()->assertJsonPath('data.status', 'CANCELLED');
    }

    public function test_keep_paid_is_refused_when_the_order_cannot_take_the_money(): void
    {
        $this->admin();
        $duplicate = $this->flagged('FAILED');
        Payment::factory()->for($duplicate->order)->paid()->create(['attempt_number' => 2]);
        $partly = $this->flagged('FAILED', ['PENDING', 'CANCELLED']);
        $mismatch = $this->flagged('FAILED', ['PENDING'], ['amount' => '1.00']);
        foreach ([$duplicate, $partly, $mismatch] as $payment) {
            $this->getJson('/api/v1/admin/payments/'.$payment->id)->assertJsonPath('data.can_keep_paid', false);
            $this->postJson('/api/v1/admin/payments/'.$payment->id.'/reconciliation', ['outcome' => 'KEEP_PAID', 'note' => 'Thử giữ', 'expected_status' => 'FAILED'])->assertConflict();
            $this->assertSame('FAILED', $payment->refresh()->status->value);
        }
        $this->getJson('/api/v1/admin/payments/'.$duplicate->id)->assertJsonPath('data.order.has_other_paid_payment', true);
    }

    public function test_an_admin_cannot_settle_a_payment_on_their_own_order(): void
    {
        $payment = $this->flagged();
        $admin = $this->admin();
        $payment->order->forceFill(['buyer_id' => $admin->id])->save();
        $this->getJson('/api/v1/admin/payments/'.$payment->id)->assertOk()->assertJsonPath('data.can_resolve', false);
        $this->postJson('/api/v1/admin/payments/'.$payment->id.'/reconciliation', ['outcome' => 'REFUNDED_OUTSIDE', 'note' => 'Tự xử lý', 'expected_status' => 'FAILED'])->assertForbidden();
        $this->assertNull($payment->refresh()->reconciliation_resolved_at);
    }

    public function test_reconcile_command_never_queries_flagged_or_resolved_payments(): void
    {
        config(['zalopay.app_id' => '2553', 'zalopay.key1' => 'k1', 'zalopay.key2' => 'k2']);
        Http::fake();
        $kept = $this->flagged('FAILED');
        $refunded = $this->flagged('CANCELLED', ['CANCELLED']);
        $this->flagged('FAILED', ['PENDING'], ['expires_at' => now()->addHour()]);
        $this->admin();
        $this->postJson('/api/v1/admin/payments/'.$kept->id.'/reconciliation', ['outcome' => 'KEEP_PAID', 'note' => 'Giữ', 'expected_status' => 'FAILED'])->assertOk();
        $this->postJson('/api/v1/admin/payments/'.$refunded->id.'/reconciliation', ['outcome' => 'REFUNDED_OUTSIDE', 'note' => 'Hoàn', 'expected_status' => 'CANCELLED'])->assertOk();
        $this->artisan('zalopay:reconcile')->expectsOutputToContain('Queried 0 payment attempts.')->assertSuccessful();
        Http::assertNothingSent();
        $this->assertSame(['PAID', 'REFUNDED'], [$kept->refresh()->status->value, $refunded->refresh()->status->value]);
    }

    public function test_admin_cancels_an_auction_with_bids_and_tells_every_participant_once(): void
    {
        $auction = Auction::factory()->create();
        [$first, $second] = User::factory()->count(2)->create();
        Bid::factory()->for($auction)->create(['user_id' => $first->id, 'amount' => '100.00']);
        Bid::factory()->for($auction)->create(['user_id' => $second->id, 'amount' => '110.00']);
        Bid::factory()->for($auction)->create(['user_id' => $first->id, 'amount' => '120.00']);
        $auction->forceFill(['current_price' => '120.00'])->save();
        $seller = User::find($auction->seller_id);

        $this->login($seller);
        $this->postJson('/api/v1/seller/auctions/'.$auction->id.'/cancel')->assertConflict();
        $this->assertSame('LIVE', $auction->refresh()->status->value);

        $admin = $this->admin();
        Event::fake([AuctionStatusChanged::class, NotificationCreated::class]);
        $url = '/api/v1/admin/auctions/'.$auction->id.'/cancel';
        $this->postJson($url, ['expected_status' => 'LIVE'])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->postJson($url, ['reason' => '   ', 'expected_status' => 'LIVE'])->assertUnprocessable();
        $this->postJson($url, ['reason' => 'Vi phạm', 'expected_status' => 'SCHEDULED'])->assertConflict();
        $this->assertSame('LIVE', $auction->refresh()->status->value);
        Event::assertNotDispatched(AuctionStatusChanged::class);

        $reason = trim('Sản phẩm vi phạm chính sách hàng giả. '.str_repeat('Chi tiết nội bộ. ', 20));
        $this->postJson($url, ['reason' => $reason, 'expected_status' => 'LIVE'])->assertOk()->assertJsonPath('data.status', 'CANCELLED')->assertJsonPath('data.bid_count', 3)->assertJsonPath('data.can_cancel', false);
        $this->assertSame(3, $auction->bids()->count());
        $this->assertSame('120.00', $auction->refresh()->current_price);
        foreach ([$seller, $first, $second] as $user) {
            $this->assertSame(1, $this->notifications($user->id, 'AUCTION_CANCELLED'));
        }
        $message = json_decode(DB::table('notifications')->where('notifiable_id', $first->id)->value('data'), true)['message'];
        $this->assertStringContainsString('Lý do: Sản phẩm vi phạm chính sách hàng giả.', $message);
        $this->assertLessThan(260, mb_strlen($message));
        $this->assertSame(0, $this->notifications($admin->id, 'AUCTION_CANCELLED'));
        $audit = $this->lastAudit('auctions', $auction->id);
        $this->assertSame(['auction.cancelled_by_admin', 'LIVE', 'CANCELLED', $admin->id, $reason], [$audit->action, $audit->from_status, $audit->to_status, $audit->actor_id, $audit->reason]);
        Event::assertDispatchedTimes(AuctionStatusChanged::class, 1);
        Event::assertDispatchedTimes(NotificationCreated::class, 3);

        // The product is free again and a repeated request reports the stale status instead of acting twice.
        $this->postJson($url, ['reason' => 'Lần hai', 'expected_status' => 'LIVE'])->assertConflict();
        $this->postJson($url, ['reason' => 'Lần hai', 'expected_status' => 'CANCELLED'])->assertConflict();
        $this->assertSame(1, $this->notifications($first->id, 'AUCTION_CANCELLED'));
        $this->assertNull($auction->refresh()->active_product_id);
        $this->login($seller);
        $this->getJson('/api/v1/seller/products/'.$auction->product_id)->assertJsonPath('data.auction_locked', false);
    }

    public function test_admin_cancellation_events_and_notifications_wait_for_the_commit(): void
    {
        $auction = Auction::factory()->create();
        $bid = Bid::factory()->for($auction)->create();
        $admin = User::factory()->admin()->create();
        Event::fake([AuctionStatusChanged::class, NotificationCreated::class]);
        DB::beginTransaction();
        app(AuctionService::class)->cancelByAdmin($admin, $auction, ['reason' => 'Thử nghiệm', 'expected_status' => 'LIVE']);
        Event::assertNotDispatched(AuctionStatusChanged::class);
        Event::assertNotDispatched(NotificationCreated::class);
        DB::rollBack();
        Event::assertNotDispatched(AuctionStatusChanged::class);
        Event::assertNotDispatched(NotificationCreated::class);
        $this->assertSame('LIVE', $auction->refresh()->status->value);
        $this->assertSame(0, $this->notifications($bid->user_id, 'AUCTION_CANCELLED'));
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_admin_cannot_cancel_finished_or_own_auctions_but_can_cancel_scheduled_and_overdue_ones(): void
    {
        $admin = $this->admin();
        $ended = $this->awaitingCheckout();
        $noWinner = Auction::factory()->create(['status' => 'ENDED', 'starts_at' => now()->subHours(2), 'ends_at' => now()->subHour()]);
        $cancelled = Auction::factory()->create(['status' => 'CANCELLED']);
        foreach ([$ended, $noWinner, $cancelled] as $auction) {
            $this->postJson('/api/v1/admin/auctions/'.$auction->id.'/cancel', ['reason' => 'Không hợp lệ', 'expected_status' => $auction->status->value])->assertConflict();
        }
        $this->assertSame('AWAITING_CHECKOUT', $ended->refresh()->settlementState());

        $own = Auction::factory()->create();
        $own->forceFill(['seller_id' => $admin->id])->save();
        $this->postJson('/api/v1/admin/auctions/'.$own->id.'/cancel', ['reason' => 'Của tôi', 'expected_status' => 'LIVE'])->assertForbidden();
        $this->postJson('/api/v1/admin/auctions/999999/cancel', ['reason' => 'Không có', 'expected_status' => 'LIVE'])->assertNotFound();

        $scheduled = Auction::factory()->create(['status' => 'SCHEDULED', 'starts_at' => now()->addHour(), 'ends_at' => now()->addHours(2)]);
        $overdue = Auction::factory()->create(['starts_at' => now()->subHour(), 'ends_at' => now()->subMinutes(10)]);
        $this->postJson('/api/v1/admin/auctions/'.$scheduled->id.'/cancel', ['reason' => 'Dừng trước giờ mở', 'expected_status' => 'SCHEDULED'])->assertOk()->assertJsonPath('data.status', 'CANCELLED');
        $this->postJson('/api/v1/admin/auctions/'.$overdue->id.'/cancel', ['reason' => 'Quá hạn', 'expected_status' => 'LIVE'])->assertOk()->assertJsonPath('data.overdue', false);
    }

    public function test_admin_auction_list_filters_by_status_settlement_and_overdue(): void
    {
        $live = Auction::factory()->create();
        $overdue = Auction::factory()->create(['starts_at' => now()->subHour(), 'ends_at' => now()->subMinutes(5)]);
        $awaiting = $this->awaitingCheckout();
        $expired = $this->awaitingCheckout();
        $expired->forceFill(['winner_checkout_expires_at' => now()->subMinute()])->save();
        $noWinner = Auction::factory()->create(['status' => 'ENDED', 'starts_at' => now()->subHours(2), 'ends_at' => now()->subHour()]);
        $ordered = $this->awaitingCheckout();
        $ordered->forceFill(['winner_order_id' => Order::factory()->create()->id])->save();
        $this->admin();
        $ids = fn (string $query): array => collect($this->getJson('/api/v1/admin/auctions?per_page=100&'.$query)->assertOk()->json('data'))->pluck('id')->sort()->values()->all();
        $expect = fn (Auction ...$rows): array => collect($rows)->map(fn (Auction $a): string => (string) $a->id)->sort()->values()->all();
        $this->assertSame($expect($live, $overdue), $ids('status=LIVE'));
        $this->assertSame($expect($overdue), $ids('overdue=1'));
        $this->assertSame($expect($awaiting), $ids('settlement=AWAITING_CHECKOUT'));
        $this->assertSame($expect($expired), $ids('settlement=CHECKOUT_EXPIRED'));
        $this->assertSame($expect($noWinner), $ids('settlement=NO_WINNER'));
        $this->assertSame($expect($ordered), $ids('settlement=ORDER_CREATED'));
        $this->assertCount(6, $ids(''));
        $row = collect($this->getJson('/api/v1/admin/auctions?overdue=1')->json('data'))->first();
        $this->assertTrue($row['overdue']);
        $this->assertTrue($row['can_cancel']);
        $this->assertSame((string) $overdue->seller_id, $row['seller']['id']);
        $this->assertArrayNotHasKey('email', $row['seller']);
        $this->getJson('/api/v1/admin/auctions?settlement=PENDING_CHECKOUT')->assertUnprocessable();
        $this->getJson('/api/v1/admin/auctions?status=OPEN')->assertUnprocessable();
    }

    public function test_admin_cannot_hide_a_product_with_a_running_auction(): void
    {
        $this->admin();
        foreach (['SCHEDULED', 'LIVE'] as $status) {
            $auction = Auction::factory()->create($status === 'SCHEDULED' ? ['status' => 'SCHEDULED', 'starts_at' => now()->addHour(), 'ends_at' => now()->addHours(2)] : []);
            $this->patchJson('/api/v1/admin/products/'.$auction->product_id.'/status', ['expected_status' => 'ACTIVE', 'status' => 'HIDDEN', 'reason' => 'Vi phạm'])
                ->assertConflict()->assertJsonPath('message', 'Sản phẩm đang có phiên đấu giá. Hãy hủy phiên trước khi ẩn.')->assertJsonPath('auction_id', (string) $auction->id);
            $this->assertSame('ACTIVE', Product::find($auction->product_id)->status->value);
            $this->assertSame($status, $auction->refresh()->status->value);
            // Cancelling first is the documented path.
            $this->postJson('/api/v1/admin/auctions/'.$auction->id.'/cancel', ['reason' => 'Vi phạm', 'expected_status' => $status])->assertOk();
            $this->patchJson('/api/v1/admin/products/'.$auction->product_id.'/status', ['expected_status' => 'ACTIVE', 'status' => 'HIDDEN', 'reason' => 'Vi phạm'])->assertOk()->assertJsonPath('data.status', 'HIDDEN');
        }
        $this->assertSame(0, DB::table('notifications')->where('type', 'AUCTION_CHECKOUT_UNAVAILABLE')->count());
    }

    public function test_hiding_a_product_awaiting_winner_checkout_notifies_the_winner_and_blocks_checkout(): void
    {
        $auction = $this->awaitingCheckout();
        $winner = User::find($auction->winner_user_id);
        $address = Address::factory()->for($winner, 'user')->create();
        $this->admin();
        Event::fake([NotificationCreated::class]);
        $this->patchJson('/api/v1/admin/products/'.$auction->product_id.'/status', ['expected_status' => 'ACTIVE', 'status' => 'HIDDEN', 'reason' => 'Hàng cấm'])->assertOk()->assertJsonPath('data.status', 'HIDDEN');
        $this->assertSame(1, $this->notifications($winner->id, 'AUCTION_CHECKOUT_UNAVAILABLE'));
        Event::assertDispatchedTimes(NotificationCreated::class, 1);
        $this->assertSame('ENDED', $auction->refresh()->status->value);

        $this->login($winner);
        $this->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('data.0.type', 'AUCTION_CHECKOUT_UNAVAILABLE')->assertJsonPath('data.0.target_route', '/auctions');
        $this->getJson('/api/v1/auctions/'.$auction->id.'/checkout')->assertOk()->assertJsonPath('data.product_available', false);
        $this->withHeaders(['Idempotency-Key' => (string) Str::uuid()])->postJson('/api/v1/auctions/'.$auction->id.'/checkout', ['address_id' => $address->id, 'payment_method' => 'COD'])->assertConflict();
        $this->assertDatabaseCount('orders', 0);
        $this->assertNull($auction->refresh()->winner_order_id);
    }

    public function test_suspending_a_shop_cancels_its_running_auctions_in_the_same_transaction(): void
    {
        $shop = Shop::factory()->create();
        $make = fn (array $state = []): Auction => Auction::factory()->for(Product::factory()->active()->for($shop)->create())->create($state);
        $live = $make();
        $bid = Bid::factory()->for($live)->create();
        $scheduled = $make(['status' => 'SCHEDULED', 'starts_at' => now()->addHour(), 'ends_at' => now()->addHours(2)]);
        $ended = $this->awaitingCheckout($make());
        $foreign = Auction::factory()->create();
        $admin = $this->admin();
        $this->patchJson('/api/v1/admin/shops/'.$shop->id.'/status', ['expected_status' => 'ACTIVE', 'status' => 'SUSPENDED', 'reason' => 'Nhiều báo cáo lừa đảo'])->assertOk()->assertJsonPath('data.status', 'SUSPENDED');
        $this->assertSame(['CANCELLED', 'CANCELLED', 'ENDED', 'LIVE'], [$live->refresh()->status->value, $scheduled->refresh()->status->value, $ended->refresh()->status->value, $foreign->refresh()->status->value]);
        $this->assertSame(1, $this->notifications($bid->user_id, 'AUCTION_CANCELLED'));
        $this->assertSame(2, $this->notifications($shop->owner_id, 'AUCTION_CANCELLED'));
        $this->assertStringContainsString('Lý do: Gian hàng bị tạm đình chỉ', json_decode(DB::table('notifications')->where('notifiable_id', $bid->user_id)->value('data'), true)['message']);
        $audit = $this->lastAudit('auctions', $live->id);
        $this->assertSame(['auction.cancelled_shop_suspended', 'LIVE', 'CANCELLED', $admin->id], [$audit->action, $audit->from_status, $audit->to_status, $audit->actor_id]);
        $this->assertSame(1, $live->bids()->count());

        // Reactivation does not resurrect the cancelled auctions.
        $this->patchJson('/api/v1/admin/shops/'.$shop->id.'/status', ['expected_status' => 'SUSPENDED', 'status' => 'ACTIVE', 'reason' => 'Đã khắc phục'])->assertOk();
        $this->assertSame('CANCELLED', $live->refresh()->status->value);
    }

    public function test_a_rejected_suspension_leaves_auctions_untouched(): void
    {
        $shop = Shop::factory()->create();
        $auction = Auction::factory()->for(Product::factory()->active()->for($shop)->create())->create();
        $this->admin();
        $this->patchJson('/api/v1/admin/shops/'.$shop->id.'/status', ['expected_status' => 'PENDING', 'status' => 'SUSPENDED', 'reason' => 'Sai trạng thái'])->assertConflict();
        $this->patchJson('/api/v1/admin/shops/'.$shop->id.'/status', ['expected_status' => 'ACTIVE', 'status' => 'SUSPENDED'])->assertUnprocessable();
        $this->assertSame('LIVE', $auction->refresh()->status->value);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_report_note_can_be_updated_without_changing_status(): void
    {
        $report = Report::factory()->create(['status' => 'IN_REVIEW', 'admin_note' => 'Ghi chú đầu']);
        $admin = $this->admin();
        $url = '/api/v1/admin/reports/'.$report->id.'/status';
        $this->patchJson($url, ['status' => 'IN_REVIEW', 'expected_status' => 'IN_REVIEW', 'admin_note' => 'Đã liên hệ người bán'])->assertOk()->assertJsonPath('data.status', 'IN_REVIEW')->assertJsonPath('data.admin_note', 'Đã liên hệ người bán');
        $audit = $this->lastAudit('reports', $report->id);
        $this->assertSame(['report.note_updated', 'IN_REVIEW', 'IN_REVIEW', $admin->id, null], [$audit->action, $audit->from_status, $audit->to_status, $audit->actor_id, $audit->reason]);
        $this->patchJson($url, ['status' => 'IN_REVIEW', 'expected_status' => 'IN_REVIEW', 'admin_note' => 'Đã liên hệ người bán'])->assertOk();
        $this->assertSame(1, AuditLog::where('entity_type', 'reports')->count());
        // A retried transition with the previous status stays idempotent and never overwrites the note.
        $this->patchJson($url, ['status' => 'IN_REVIEW', 'expected_status' => 'PENDING', 'admin_note' => 'Phiên bản cũ'])->assertOk()->assertJsonPath('data.admin_note', 'Đã liên hệ người bán');
        $this->assertSame(1, AuditLog::where('entity_type', 'reports')->count());
        $this->assertSame('Đã liên hệ người bán', $report->refresh()->admin_note);
        $this->assertNull($report->handled_at);

        $closed = Report::factory()->create(['status' => 'RESOLVED', 'admin_note' => null]);
        $this->patchJson('/api/v1/admin/reports/'.$closed->id.'/status', ['status' => 'RESOLVED', 'expected_status' => 'RESOLVED', 'admin_note' => 'Bổ sung sau khi đóng'])->assertOk()->assertJsonPath('data.admin_note', 'Bổ sung sau khi đóng');
        $this->patchJson('/api/v1/admin/reports/'.$closed->id.'/status', ['status' => 'RESOLVED', 'expected_status' => 'RESOLVED'])->assertOk()->assertJsonPath('data.admin_note', null);
        $this->assertDatabaseCount('notifications', 0);
        $this->login(User::find($report->reporter_user_id));
        $this->getJson('/api/v1/my-reports/'.$report->id)->assertOk()->assertJsonMissingPath('data.admin_note')->assertJsonMissingPath('data.can_moderate');
    }

    public function test_admin_cannot_rule_on_reports_against_their_own_content(): void
    {
        $admin = $this->admin();
        $shop = Shop::factory()->for($admin, 'owner')->create();
        $product = Product::factory()->active()->for($shop)->create();
        $comment = VideoComment::factory()->create(['user_id' => $admin->id]);
        $own = [
            Report::factory()->create(['target_type' => 'PRODUCT', 'target_id' => $product->id]),
            Report::factory()->create(['target_type' => 'SHOP', 'target_id' => $shop->id]),
            Report::factory()->create(['target_type' => 'VIDEO_COMMENT', 'target_id' => $comment->id]),
        ];
        $foreign = Report::factory()->create();
        $gone = Report::factory()->create(['target_type' => 'VIDEO_COMMENT', 'target_id' => 987654]);
        foreach ($own as $report) {
            $this->getJson('/api/v1/admin/reports/'.$report->id)->assertOk()->assertJsonPath('data.can_moderate', false);
            $this->patchJson('/api/v1/admin/reports/'.$report->id.'/status', ['status' => 'DISMISSED', 'expected_status' => 'PENDING', 'admin_note' => 'Tự bác'])->assertForbidden();
            $this->patchJson('/api/v1/admin/reports/'.$report->id.'/status', ['status' => 'PENDING', 'expected_status' => 'PENDING', 'admin_note' => 'Tự ghi chú'])->assertForbidden();
            $this->assertSame(['PENDING', null], [$report->refresh()->status->value, $report->admin_note]);
        }
        $list = collect($this->getJson('/api/v1/admin/reports?per_page=100')->assertOk()->json('data'))->pluck('can_moderate', 'id');
        $this->assertSame([false, false, false, true, true], [$list[(string) $own[0]->id], $list[(string) $own[1]->id], $list[(string) $own[2]->id], $list[(string) $foreign->id], $list[(string) $gone->id]]);
        $this->patchJson('/api/v1/admin/reports/'.$foreign->id.'/status', ['status' => 'IN_REVIEW', 'expected_status' => 'PENDING'])->assertOk()->assertJsonPath('data.can_moderate', true);

        // Another Admin can still handle them.
        $this->admin();
        $this->patchJson('/api/v1/admin/reports/'.$own[0]->id.'/status', ['status' => 'DISMISSED', 'expected_status' => 'PENDING', 'admin_note' => 'Không vi phạm'])->assertOk();
    }

    public function test_reports_status_is_protected_by_a_check_constraint(): void
    {
        $report = Report::factory()->create();
        try {
            DB::table('reports')->where('id', $report->id)->update(['status' => 'ARCHIVED']);
            $this->fail('An unknown report status was stored.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('reports_status_enum_check', $exception->getMessage());
        }
        foreach (['IN_REVIEW', 'RESOLVED', 'DISMISSED', 'PENDING'] as $status) {
            DB::table('reports')->where('id', $report->id)->update(['status' => $status]);
        }
        $this->assertSame('PENDING', $report->refresh()->status->value);
    }

    public function test_shop_edits_are_audited_by_field_name_without_triggering_review(): void
    {
        $shop = Shop::factory()->create(['name' => 'Tên cũ', 'description' => 'Mô tả cũ']);
        $this->login($shop->owner);
        $secret = 'Nội dung mô tả rất dài không được ghi vào nhật ký';
        $this->patchJson('/api/v1/seller/shop', ['name' => 'Tên mới', 'description' => $secret])->assertOk()->assertJsonPath('data.status', 'ACTIVE')->assertJsonPath('data.name', 'Tên mới');
        $audit = $this->lastAudit('shops', $shop->id);
        $this->assertSame(['shop.updated', 'ACTIVE', 'ACTIVE', $shop->owner_id, 'fields: name, description'], [$audit->action, $audit->from_status, $audit->to_status, $audit->actor_id, $audit->reason]);
        $this->patchJson('/api/v1/seller/shop', ['name' => 'Tên mới'])->assertOk();
        $this->assertSame(1, AuditLog::where('entity_type', 'shops')->count());
        $this->patchJson('/api/v1/seller/shop', ['description' => null])->assertOk();
        $this->assertSame('fields: description', $this->lastAudit('shops', $shop->id)->reason);
        $this->assertStringNotContainsString($secret, AuditLog::pluck('reason')->implode('|'));
        $this->assertSame('ACTIVE', $shop->refresh()->status->value);
    }
}
