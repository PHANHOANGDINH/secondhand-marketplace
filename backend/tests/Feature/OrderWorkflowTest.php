<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\ShopOrderStatus;
use App\Enums\ShopStatus;
use App\Models\Address;
use App\Models\AuditLog;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShopOrder;
use App\Models\User;
use App\Services\AuditService;
use App\Services\CheckoutService;
use App\Services\ShopOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class OrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeaders(['Origin' => 'http://localhost:5173', 'Accept' => 'application/json']);
    }

    private function fixture(int $count = 1): array
    {
        $buyer = User::factory()->create();
        $address = Address::factory()->for($buyer, 'user')->create();
        $cart = Cart::factory()->for($buyer, 'user')->create();
        $products = [];
        for ($i = 0; $i < $count; $i++) {
            $p = Product::factory()->active()->create(['stock_quantity' => 1]);
            CartItem::factory()->for($cart)->for($p)->create(['quantity' => 1]);
            $products[] = $p;
        } $order = app(CheckoutService::class)->checkout($buyer, $address->id, (string) Str::uuid())['order'];

        return [$buyer, $order, $products];
    }

    private function login(User $u): void
    {
        $this->app['session']->flush();
        Auth::forgetGuards();
        $this->actingAs($u, 'web');
    }

    private function change(ShopOrder $part, string $from, string $to, array $extra = []): TestResponse
    {
        return $this->patchJson('/api/v1/seller/orders/'.$part->id.'/status', ['expected_status' => $from, 'status' => $to, ...$extra]);
    }

    private function cancel(Order $order, ShopOrder $part, string $expected = 'PENDING'): TestResponse
    {
        return $this->postJson('/api/v1/orders/'.$order->id.'/shop-orders/'.$part->id.'/cancel', ['expected_status' => $expected, 'reason' => 'Changed plans']);
    }

    public function test_seller_lifecycle_stale_conflict_and_full_cod_collection(): void
    {
        [$buyer,$order,$products] = $this->fixture(2);
        $parts = $order->shopOrders;
        foreach ($parts as $index => $part) {
            $this->login($products[$index]->shop->owner);
            $this->change($part, 'PENDING', 'SHIPPING')->assertConflict();
            $this->change($part, 'PENDING', 'CONFIRMED')->assertOk();
            $audit = AuditLog::count();
            $this->change($part, 'PENDING', 'CONFIRMED')->assertOk();
            $this->assertSame($audit, AuditLog::count());
            $this->change($part, 'PENDING', 'CANCELLED', ['reason' => 'Unavailable', 'failure_category' => 'DAMAGED'])->assertConflict();
            $this->change($part, 'CONFIRMED', 'PROCESSING')->assertOk();
            $this->change($part, 'PROCESSING', 'SHIPPING')->assertOk();
            $this->change($part, 'SHIPPING', 'CANCELLED', ['reason' => 'No', 'failure_category' => 'DAMAGED'])->assertConflict();
            $this->change($part, 'SHIPPING', 'COMPLETED')->assertUnprocessable();
            $this->change($part, 'SHIPPING', 'COMPLETED', ['cod_collected' => true])->assertOk();
            if ($index === 0) {
                $this->assertSame(PaymentStatus::PENDING, $order->payments()->first()->status);
            }
        }
        $this->assertSame(PaymentStatus::PAID, $order->payments()->first()->status);
        $this->assertNotNull($order->payments()->first()->paid_at);
        $this->login($buyer);
        $this->getJson('/api/v1/orders/'.$order->id)->assertJsonPath('data.overall_status', 'COMPLETED')->assertJsonPath('data.amount_due', '0.00')->assertJsonPath('data.cod_collected_amount', '200000.00');
    }

    public function test_buyer_partial_cancel_restores_once_and_replaces_pending_cod_attempt(): void
    {
        [$buyer,$order,$products] = $this->fixture(2);
        $part = $order->shopOrders[0];
        $this->login($buyer);
        $this->cancel($order, $part)->assertOk()->assertJsonPath('data.total_amount', '200000.00')->assertJsonPath('data.payable_amount', '100000.00')->assertJsonCount(2, 'data.payments');
        $this->assertSame(1, $products[0]->refresh()->stock_quantity);
        $this->assertSame(ProductStatus::ACTIVE, $products[0]->status);
        $this->assertNotNull($part->refresh()->stock_restored_at);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'attempt_number' => 1, 'status' => 'CANCELLED', 'amount' => '200000.00']);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'attempt_number' => 2, 'status' => 'PENDING', 'amount' => '100000.00']);
        $audit = AuditLog::count();
        $timestamp = $part->updated_at->format('Y-m-d H:i:s.u');
        $this->cancel($order, $part)->assertOk();
        $this->assertSame($audit, AuditLog::count());
        $this->assertSame(1, $products[0]->refresh()->stock_quantity);
        $this->assertSame($timestamp, $part->refresh()->updated_at->format('Y-m-d H:i:s.u'));
        $this->cancel($order, $order->shopOrders[1])->assertOk()->assertJsonPath('data.overall_status', 'CANCELLED')->assertJsonPath('data.payment_status', 'CANCELLED')->assertJsonPath('data.payable_amount', '0.00');
        $this->assertSame(0, $order->payments()->where('status', 'PENDING')->count());
    }

    public function test_cancel_remaining_part_after_other_delivery_settles_replacement_payment(): void
    {
        [$buyer,$order,$products] = $this->fixture(2);
        $first = $order->shopOrders[0];
        $this->login($products[0]->shop->owner);
        foreach ([['PENDING', 'CONFIRMED'], ['CONFIRMED', 'PROCESSING'], ['PROCESSING', 'SHIPPING'], ['SHIPPING', 'COMPLETED']] as [$from,$to]) {
            $this->change($first, $from, $to, $to === 'COMPLETED' ? ['cod_collected' => true] : [])->assertOk();
        }
        $this->login($buyer);
        $this->cancel($order, $order->shopOrders[1])->assertOk()->assertJsonPath('data.overall_status', 'PARTIALLY_COMPLETED')->assertJsonPath('data.payment_status', 'PAID')->assertJsonPath('data.amount_due', '0.00');
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'attempt_number' => 2, 'status' => 'PAID', 'amount' => '100000.00']);
    }

    public function test_cancellation_does_not_bypass_moderation_or_restore_deleted_visibility(): void
    {
        foreach (['edited', 'suspended', 'hidden', 'deleted'] as $mode) {
            [$buyer,$order,$products] = $this->fixture();
            $p = $products[0];
            if ($mode === 'edited') {
                $p->update(['description' => 'Updated after selling out']);
            }
            if ($mode === 'suspended') {
                $p->shop->update(['status' => ShopStatus::SUSPENDED]);
            }
            if ($mode === 'hidden') {
                $p->update(['status' => ProductStatus::HIDDEN]);
            }
            if ($mode === 'deleted') {
                $p->delete();
            }
            $this->login($buyer);
            $this->cancel($order, $order->shopOrders[0])->assertOk();
            $this->assertSame(1, $p->refresh()->stock_quantity);
            $this->assertSame($mode === 'deleted' ? ProductStatus::SOLD : ProductStatus::HIDDEN, $p->status);
            $this->assertSame($mode === 'deleted', $p->trashed());
        }
    }

    public function test_buyer_cancel_rules_parent_binding_and_seller_restrictions(): void
    {
        [$buyer,$order,$products] = $this->fixture();
        $part = $order->shopOrders[0];
        $this->login(User::factory()->create());
        $this->cancel($order, $part)->assertForbidden();
        $other = ShopOrder::factory()->create();
        $this->login($buyer);
        $this->cancel($order, $other)->assertNotFound();
        $seller = $products[0]->shop->owner;
        $this->login($seller);
        $this->change($part, 'PENDING', 'CONFIRMED')->assertOk();
        $this->login($buyer);
        $this->cancel($order, $part)->assertConflict();
        $this->cancel($order, $part, 'CONFIRMED')->assertOk();
        [$buyer,$order,$products] = $this->fixture();
        $part = $order->shopOrders[0];
        $this->login($products[0]->shop->owner);
        $products[0]->shop->update(['status' => ShopStatus::SUSPENDED]);
        $this->change($part, 'PENDING', 'CONFIRMED')->assertForbidden();
        $this->getJson('/api/v1/seller/orders/'.$part->id)->assertOk()->assertJsonPath('data.can_update', false);
        $products[0]->shop->update(['status' => ShopStatus::ACTIVE]);
        $this->change($part, 'PENDING', 'CONFIRMED')->assertOk();
        $this->change($part, 'CONFIRMED', 'PROCESSING')->assertOk();
        $this->login($buyer);
        $this->cancel($order, $part, 'PROCESSING')->assertConflict();
        $this->login($products[0]->shop->owner);
        $this->change($part, 'PROCESSING', 'CANCELLED', ['reason' => 'Damaged'])->assertUnprocessable();
        $this->change($part, 'PROCESSING', 'CANCELLED', ['reason' => 'Damaged', 'failure_category' => 'DAMAGED'])->assertOk();
    }

    public function test_cancellation_audit_failure_rolls_back_stock_status_and_payment(): void
    {
        [$buyer,$order,$products] = $this->fixture();
        $part = $order->shopOrders[0];
        $audit = \Mockery::mock(AuditService::class);
        $audit->shouldReceive('record')->andThrow(new \RuntimeException('Injected failure'));
        $this->app->instance(AuditService::class, $audit);
        try {
            app(ShopOrderService::class)->transition($buyer, $part, ['status' => 'CANCELLED', 'expected_status' => 'PENDING', 'reason' => 'Changed'], true);
            $this->fail('Expected failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('Injected failure', $e->getMessage());
        }
        $this->assertSame(0, $products[0]->refresh()->stock_quantity);
        $this->assertSame(ShopOrderStatus::PENDING, $part->refresh()->status);
        $this->assertNull($part->stock_restored_at);
        $this->assertSame(PaymentStatus::PENDING, $order->payments()->first()->status);
        $this->assertSame(1, $order->payments()->count());
    }

    public function test_derived_overall_status_filters_match_child_states(): void
    {
        [$buyer, $order, $products] = $this->fixture(2);
        $this->login($buyer);
        foreach ([['PENDING', 'PENDING', 'PENDING'], ['CONFIRMED', 'PENDING', 'IN_PROGRESS'], ['CANCELLED', 'PENDING', 'IN_PROGRESS'], ['COMPLETED', 'CANCELLED', 'PARTIALLY_COMPLETED'], ['COMPLETED', 'COMPLETED', 'COMPLETED'], ['CANCELLED', 'CANCELLED', 'CANCELLED']] as [$first,$second,$overall]) {
            $order->shopOrders[0]->update(['status' => $first]);
            $order->shopOrders[1]->update(['status' => $second]);
            foreach (['PENDING', 'IN_PROGRESS', 'PARTIALLY_COMPLETED', 'COMPLETED', 'CANCELLED'] as $filter) {
                $this->getJson('/api/v1/orders?overall_status='.$filter)->assertOk()->assertJsonCount($filter === $overall ? 1 : 0, 'data');
            }
        }
        $this->getJson('/api/v1/orders?overall_status=bad')->assertUnprocessable();
        $this->login($products[0]->shop->owner);
        $this->getJson('/api/v1/seller/orders?to=2099-01-01')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/seller/orders?from=2026-01-02&to=2026-01-01')->assertUnprocessable();
    }
}
