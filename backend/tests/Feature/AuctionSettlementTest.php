<?php

namespace Tests\Feature;

use App\Enums\AuctionStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\ShopStatus;
use App\Models\Address;
use App\Models\Auction;
use App\Models\AuditLog;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\AuctionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AuctionSettlementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeaders(['Origin' => 'http://localhost:5173', 'Accept' => 'application/json']);
    }

    private function fixture(string $amount = '110.00', int $stock = 2): array
    {
        $auction = Auction::factory()->create();
        $auction->product->update(['stock_quantity' => $stock]);
        $winner = User::factory()->create();
        $address = Address::factory()->for($winner, 'user')->create();
        app(AuctionService::class)->placeBid($winner, $auction, $amount);
        $auction->forceFill(['ends_at' => now()->subSecond()])->save();
        $auction = app(AuctionService::class)->finalize($auction);
        $this->actingAs($winner, 'web');

        return [$auction, $winner, $address];
    }

    private function submitCheckout(Auction $auction, Address $address, array $extra = [], ?string $key = null): TestResponse
    {
        return $this->postJson('/api/v1/auctions/'.$auction->id.'/checkout', ['address_id' => $address->id, 'payment_method' => 'COD', ...$extra], ['Idempotency-Key' => $key ?? (string) Str::uuid()]);
    }

    public function test_finalization_sets_configured_deadline_without_stock_order_payment_and_is_idempotent(): void
    {
        config(['auctions.winner_checkout_hours' => 2]);
        [$auction] = $this->fixture();
        $deadline = $auction->winner_checkout_expires_at->toISOString();
        $this->assertEqualsWithDelta(7200, now()->diffInSeconds($auction->winner_checkout_expires_at), 2);
        $this->assertSame('AWAITING_CHECKOUT', $auction->settlementState());
        $this->assertSame($deadline, app(AuctionService::class)->finalize($auction)->winner_checkout_expires_at->toISOString());
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(2, $auction->product->stock_quantity);
    }

    public function test_cod_uses_bid_price_shipping_snapshot_and_one_unit_with_order_traceability(): void
    {
        [$auction, $winner, $address] = $this->fixture('110.25');
        $this->getJson('/api/v1/auctions/'.$auction->id.'/checkout')->assertOk()->assertJsonPath('data.winning_price', '110.25')->assertJsonPath('data.addresses.0.id', (string) $address->id);
        $order = $this->submitCheckout($auction, $address)->assertCreated()->assertJsonPath('data.total_amount', '110.25')->assertJsonPath('data.payment_status', 'PENDING')->assertJsonPath('data.shipping_address.address_line', $address->address_line)->assertJsonPath('data.shop_orders.0.items.0.auction_id', (string) $auction->id)->json('data');
        $item = OrderItem::sole();
        $this->assertSame(1, $item->quantity);
        $this->assertSame('110.25', $item->product_price);
        $this->assertSame(1, $auction->product->refresh()->stock_quantity);
        $this->assertSame(ProductStatus::ACTIVE, $auction->product->status);
        $this->assertSame((int) $order['id'], $auction->refresh()->winner_order_id);
        $this->assertSame('ORDER_CREATED', $auction->settlementState());
        $this->getJson('/api/v1/auctions/'.$auction->id.'/checkout')->assertOk()->assertJsonPath('data.winner_order.id', $order['id']);
    }

    public function test_retries_same_or_new_key_return_same_order_without_decrement_even_after_deadline(): void
    {
        [$auction, , $address] = $this->fixture();
        $key = (string) Str::uuid();
        $id = $this->submitCheckout($auction, $address, key: $key)->assertCreated()->json('data.id');
        $auction->forceFill(['winner_checkout_expires_at' => now()->subDay()])->save();
        $this->submitCheckout($auction, $address, key: $key)->assertOk()->assertJsonPath('data.id', $id);
        $this->submitCheckout($auction, $address)->assertOk()->assertJsonPath('data.id', $id);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertSame(1, $auction->product->refresh()->stock_quantity);
    }

    public function test_changed_payload_and_reused_fixed_checkout_key_are_rejected(): void
    {
        [$auction, $winner, $address] = $this->fixture();
        $this->submitCheckout($auction, $address)->assertCreated();
        $another = Address::factory()->for($winner, 'user')->create();
        $this->submitCheckout($auction, $another)->assertConflict();
        $key = Order::sole()->checkout_key;
        $this->postJson('/api/v1/checkout', ['address_id' => $address->id, 'payment_method' => 'COD'], ['Idempotency-Key' => $key])->assertConflict();
    }

    public function test_guest_loser_and_seller_cannot_read_or_checkout(): void
    {
        [$auction, , $address] = $this->fixture();
        foreach ([User::factory()->create(), $auction->seller] as $actor) {
            $this->app['session']->flush();
            Auth::forgetGuards();
            $this->actingAs($actor, 'web');
            $this->getJson('/api/v1/auctions/'.$auction->id.'/checkout')->assertForbidden();
            $this->submitCheckout($auction, $address)->assertForbidden();
        }
        $this->app['session']->flush();
        Auth::forgetGuards();
        $this->getJson('/api/v1/auctions/'.$auction->id.'/checkout')->assertUnauthorized();
        $this->submitCheckout($auction, $address)->assertUnauthorized();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_foreign_address_and_forbidden_client_fields_never_create_order(): void
    {
        [$auction, , $address] = $this->fixture();
        $this->submitCheckout($auction, Address::factory()->create())->assertUnprocessable()->assertJsonValidationErrors('address_id');
        foreach (['price', 'amount', 'product_id', 'seller_id', 'winner_user_id', 'auction_id', 'quantity', 'status'] as $field) {
            $this->submitCheckout($auction, $address, [$field => 1])->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->postJson('/api/v1/auctions/'.$auction->id.'/checkout', ['address_id' => $address->id, 'payment_method' => 'COD'])->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_expired_claim_releases_fixed_price_and_new_auction_without_stock_mutation(): void
    {
        [$auction, , $address] = $this->fixture();
        $auction->forceFill(['winner_checkout_expires_at' => now()])->save();
        $this->submitCheckout($auction, $address)->assertConflict();
        $this->assertSame('CHECKOUT_EXPIRED', $auction->settlementState());
        $this->postJson('/api/v1/cart/items', ['product_id' => $auction->product_id, 'quantity' => 1])->assertCreated();
        $this->assertSame(2, $auction->product->refresh()->stock_quantity);
        $next = app(AuctionService::class)->create($auction->seller, ['product_id' => $auction->product_id, 'start_price' => '100', 'bid_increment' => '10', 'starts_at' => now()->toISOString(), 'ends_at' => now()->addHour()->toISOString()]);
        $this->assertSame(AuctionStatus::LIVE, $next->status);
    }

    public function test_live_scheduled_and_winner_claim_block_cart_and_fixed_checkout_preserving_cart(): void
    {
        [$auction, $winner, $address] = $this->fixture();
        $cart = Cart::factory()->for($winner, 'user')->create();
        CartItem::factory()->for($cart)->for($auction->product)->create(['quantity' => 1]);
        foreach ([AuctionStatus::LIVE, AuctionStatus::SCHEDULED, AuctionStatus::ENDED] as $status) {
            $auction->forceFill(['status' => $status, 'winner_user_id' => $status === AuctionStatus::ENDED ? $winner->id : null, 'winning_bid_id' => $status === AuctionStatus::ENDED ? $auction->bids()->sole()->id : null])->save();
            $this->getJson('/api/v1/cart')->assertOk()->assertJsonPath('data.items.0.unavailable_reason', 'AUCTION_RESERVED');
            $this->postJson('/api/v1/cart/items', ['product_id' => $auction->product_id, 'quantity' => 1])->assertConflict();
            $this->postJson('/api/v1/checkout', ['address_id' => $address->id, 'payment_method' => 'COD'], ['Idempotency-Key' => (string) Str::uuid()])->assertConflict();
        }
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_valid_winner_claim_prevents_new_auction(): void
    {
        [$auction] = $this->fixture();
        $this->actingAs($auction->seller, 'web');
        $this->postJson('/api/v1/seller/auctions', ['product_id' => $auction->product_id, 'start_price' => '100', 'bid_increment' => '10', 'starts_at' => now()->toISOString(), 'ends_at' => now()->addHour()->toISOString()])->assertConflict();
    }

    public function test_no_winner_ends_without_claim_and_releases_fixed_purchase(): void
    {
        $auction = Auction::factory()->create(['ends_at' => now()->subSecond()]);
        $auction = app(AuctionService::class)->finalize($auction);
        $this->assertSame('NO_WINNER', $auction->settlementState());
        $this->assertNull($auction->winner_checkout_expires_at);
        $this->actingAs(User::factory()->create(), 'web');
        $this->postJson('/api/v1/cart/items', ['product_id' => $auction->product_id, 'quantity' => 1])->assertCreated();
    }

    public function test_hidden_product_and_inactive_shop_cannot_be_fulfilled(): void
    {
        [$auction, , $address] = $this->fixture();
        $auction->product->update(['status' => ProductStatus::HIDDEN]);
        $this->submitCheckout($auction, $address)->assertConflict();
        $auction->product->update(['status' => ProductStatus::ACTIVE]);
        $auction->product->shop->update(['status' => ShopStatus::SUSPENDED]);
        $this->submitCheckout($auction, $address)->assertConflict();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_sold_stock_and_cancel_restore_exactly_once_without_reopening_auction_checkout(): void
    {
        [$auction, , $address] = $this->fixture(stock: 1);
        $order = $this->submitCheckout($auction, $address)->assertCreated()->json('data');
        $this->assertSame(ProductStatus::SOLD, $auction->product->refresh()->status);
        $url = '/api/v1/orders/'.$order['id'].'/shop-orders/'.$order['shop_orders'][0]['id'].'/cancel';
        $data = ['expected_status' => 'PENDING', 'reason' => 'No longer needed'];
        $this->postJson($url, $data)->assertOk();
        $this->postJson($url, $data)->assertOk();
        $this->assertSame(1, $auction->product->refresh()->stock_quantity);
        $this->assertSame(ProductStatus::ACTIVE, $auction->product->status);
        $this->submitCheckout($auction, $address)->assertOk()->assertJsonPath('data.overall_status', 'CANCELLED');
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_remaining_stock_is_available_after_order_and_price_remains_fixed(): void
    {
        [$auction, , $address] = $this->fixture();
        $this->submitCheckout($auction, $address)->assertCreated();
        $this->postJson('/api/v1/cart/items', ['product_id' => $auction->product_id, 'quantity' => 1])->assertCreated();
        $this->postJson('/api/v1/checkout', ['address_id' => $address->id, 'payment_method' => 'COD'], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated()->assertJsonPath('data.total_amount', $auction->product->price)->assertJsonPath('data.shop_orders.0.items.0.auction_id', null);
        $this->assertSame(0, $auction->product->refresh()->stock_quantity);
    }

    public function test_zalopay_uses_winning_amount_retries_without_new_order_or_stock_and_redirect_is_not_payment(): void
    {
        config(['zalopay.app_id' => '2553', 'zalopay.key1' => 'test-key', 'zalopay.key2' => 'test-key2']);
        Http::fake(['*/v2/create' => Http::sequence()->push(['return_code' => 2, 'sub_return_code' => -401])->push(['return_code' => 1, 'order_url' => 'https://sb-openapi.zalopay.vn/payment/test'])]);
        [$auction, , $address] = $this->fixture('120000.00');
        $order = $this->submitCheckout($auction, $address, ['payment_method' => 'ZALOPAY'])->assertCreated()->assertJsonPath('data.total_amount', '120000.00')->assertJsonPath('data.payment_status', 'FAILED')->json('data');
        $this->postJson('/api/v1/orders/'.$order['id'].'/payments/zalopay', [], ['Idempotency-Key' => (string) Str::uuid()])->assertSuccessful();
        $this->getJson('/api/v1/orders/'.$order['id'])->assertOk()->assertJsonPath('data.payment_status', 'PENDING');
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('payments', 2);
        $this->assertSame(1, $auction->product->refresh()->stock_quantity);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/create') && ($request['amount'] ?? null) === 120000);
    }

    public function test_auction_payment_callback_checks_bid_amount_and_remains_idempotent(): void
    {
        config(['zalopay.app_id' => '2553', 'zalopay.key1' => 'test-key', 'zalopay.key2' => 'test-key2']);
        Http::fake(['*/v2/create' => Http::response(['return_code' => 1, 'order_url' => 'https://sb-openapi.zalopay.vn/payment/test'])]);
        [$auction, , $address] = $this->fixture('120000.00');
        $this->submitCheckout($auction, $address, ['payment_method' => 'ZALOPAY'])->assertCreated();
        $payment = Order::sole()->payments()->sole();
        $payload = ['app_id' => $payment->gateway_app_id, 'app_trans_id' => $payment->transaction_reference, 'app_user' => 'buyer-'.$payment->order->buyer_id, 'app_time' => $payment->gateway_app_time, 'amount' => 120000, 'zp_trans_id' => 123456789];
        $signed = function (array $data): array {
            $json = json_encode($data, JSON_THROW_ON_ERROR);

            return ['data' => $json, 'mac' => hash_hmac('sha256', $json, config('zalopay.key2')), 'type' => 1];
        };
        $this->postJson('/api/v1/payments/zalopay/callback', $signed([...$payload, 'amount' => 100000]))->assertOk()->assertJsonPath('return_code', -1);
        $this->assertSame(PaymentStatus::PENDING, $payment->refresh()->status);
        $this->postJson('/api/v1/payments/zalopay/callback', $signed($payload))->assertOk()->assertJsonPath('return_code', 1);
        $auditCount = AuditLog::count();
        $this->postJson('/api/v1/payments/zalopay/callback', $signed($payload))->assertOk()->assertJsonPath('return_code', 2);
        $this->assertSame($auditCount, AuditLog::count());
        $this->assertSame(PaymentStatus::PAID, $payment->refresh()->status);
        $this->assertSame(1, $auction->product->refresh()->stock_quantity);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_fractional_bid_supports_cod_and_rejects_zalopay_without_mutation(): void
    {
        config(['zalopay.app_id' => '2553', 'zalopay.key1' => 'test-key', 'zalopay.key2' => 'test-key2']);
        [$auction, , $address] = $this->fixture('110.25');
        $this->getJson('/api/v1/auctions/'.$auction->id.'/checkout')->assertOk()->assertJsonPath('data.payment_methods', ['COD']);
        $this->submitCheckout($auction, $address, ['payment_method' => 'ZALOPAY'])->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(2, $auction->product->refresh()->stock_quantity);
    }
}
