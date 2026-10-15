<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\ShopOrderStatus;
use App\Models\Address;
use App\Models\AuditLog;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\ZaloPayGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ZaloPayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['zalopay.app_id' => '123456', 'zalopay.key1' => bin2hex(random_bytes(32)), 'zalopay.key2' => bin2hex(random_bytes(32)), 'zalopay.create_url' => 'https://sb-openapi.zalopay.vn/v2/create', 'zalopay.query_url' => 'https://sb-openapi.zalopay.vn/v2/query', 'zalopay.redirect_url' => 'http://localhost:5173/payment/result', 'zalopay.callback_url' => null, 'zalopay.expire_seconds' => 900]);
        Http::preventStrayRequests();
        $this->fakeGateway(['*/v2/create' => Http::response(['return_code' => 1, 'order_url' => 'https://sbgateway.zalopay.vn/openinapp?token=test']), '*/v2/query' => Http::response(['return_code' => 3, 'is_processing' => true])]);
        $this->withHeaders(['Origin' => 'http://localhost:5173', 'Accept' => 'application/json']);
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function fakeGateway(array $responses): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake($responses);
    }

    private function signIn(User $user): void
    {
        $this->app['session']->flush();
        Auth::forgetGuards();
        $this->actingAs($user, 'web');
    }

    private function cart(int $count = 1, string $price = '125000.00'): array
    {
        $buyer = User::factory()->create();
        $address = Address::factory()->for($buyer, 'user')->create();
        $cart = Cart::factory()->for($buyer, 'user')->create();
        $products = [];
        for ($i = 0; $i < $count; $i++) {
            $p = Product::factory()->active()->create(['stock_quantity' => 3, 'price' => $price]);
            CartItem::factory()->for($cart)->for($p)->create(['quantity' => 2]);
            $products[] = $p;
        }
        $this->signIn($buyer);

        return [$buyer, $address, $products];
    }

    private function checkout(Address $address, ?string $key = null, array $extra = []): TestResponse
    {
        return $this->withHeader('Idempotency-Key', $key ?? (string) Str::uuid())->postJson('/api/v1/checkout', ['address_id' => $address->id, 'payment_method' => 'ZALOPAY', ...$extra]);
    }

    private function placed(): array
    {
        [$buyer,$address,$products] = $this->cart();
        $id = $this->checkout($address)->assertCreated()->json('data.id');

        return [$buyer, Order::findOrFail($id), $products[0]];
    }

    private function signed(Payment $payment, array $changes = []): array
    {
        $data = json_encode(['app_id' => $payment->gateway_app_id, 'app_trans_id' => $payment->transaction_reference, 'app_user' => 'buyer-'.$payment->order->buyer_id, 'app_time' => $payment->gateway_app_time, 'amount' => ZaloPayGateway::amount($payment->amount), 'zp_trans_id' => 123456789, ...$changes], JSON_THROW_ON_ERROR);

        return ['data' => $data, 'mac' => hash_hmac('sha256', $data, config('zalopay.key2')), 'type' => 1];
    }

    private function sendCallback(array $data, int $code = 1): void
    {
        $this->postJson('/api/v1/payments/zalopay/callback', $data)->assertOk()->assertJsonPath('return_code', $code);
    }

    private function syncPayment(Order $order): TestResponse
    {
        return $this->postJson('/api/v1/orders/'.$order->id.'/payments/zalopay/query');
    }

    private function retry(Order $order, ?string $key = null, array $extra = []): TestResponse
    {
        return $this->withHeader('Idempotency-Key', $key ?? (string) Str::uuid())->postJson('/api/v1/orders/'.$order->id.'/payments/zalopay', $extra);
    }

    public function test_multi_shop_checkout_db_amount_signed_v2_channels_and_idempotent_replay(): void
    {
        [$buyer,$address,$products] = $this->cart(2);
        $key = (string) Str::uuid();
        $channels = ZaloPayGateway::CHANNELS;
        $result = $this->checkout($address, $key, ['preferred_payment_method' => $channels])->assertCreated()->assertJsonPath('data.total_amount', '500000.00')->assertJsonPath('data.payment_method', 'ZALOPAY')->assertJsonPath('data.payment_status', 'PENDING')->assertJsonCount(1, 'data.payments')->assertJsonCount(2, 'data.shop_orders');
        $payment = Payment::firstOrFail();
        $this->assertMatchesRegularExpression('/^'.now('Asia/Ho_Chi_Minh')->format('ymd').'_[a-f0-9]{32}$/', $payment->transaction_reference);
        Http::assertSent(function ($request) use ($channels): bool {
            $data = $request->data();
            $sorted = $channels;
            sort($sorted);
            $expected = hash_hmac('sha256', implode('|', array_map(fn ($k) => (string) $data[$k], ['app_id', 'app_trans_id', 'app_user', 'amount', 'app_time', 'embed_data', 'item'])), config('zalopay.key1'));

            return $request->url() === config('zalopay.create_url') && $data['amount'] === 500000 && hash_equals($expected, $data['mac']) && json_decode($data['embed_data'], true)['preferred_payment_method'] === $sorted && ! isset($data['card_number']);
        });
        $this->checkout($address, $key, ['preferred_payment_method' => array_reverse($channels)])->assertOk()->assertJsonPath('data.id', $result->json('data.id'));
        Http::assertSentCount(1);
        $this->assertDatabaseCount('payments', 1);
        foreach ($products as $product) {
            $this->assertSame(1, $product->refresh()->stock_quantity);
        }
        $this->checkout($address, $key, ['preferred_payment_method' => ['vietqr']])->assertConflict();
    }

    public function test_client_cannot_inject_payment_state_amount_reference_or_invalid_channels(): void
    {
        [$buyer,$address] = $this->cart();
        foreach (['amount', 'status', 'order_id', 'app_trans_id', 'order_url', 'card_number'] as $field) {
            $this->checkout($address, null, [$field => 'PAID'])->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->checkout($address, null, ['preferred_payment_method' => ['fake']])->assertUnprocessable();
        $this->checkout($address, null, ['preferred_payment_method' => ['vietqr', 'vietqr']])->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        Http::assertNothingSent();
    }

    public function test_fractional_vnd_and_missing_configuration_roll_back_before_stock_changes(): void
    {
        [$buyer,$address,$products] = $this->cart(1, '0.10');
        $this->checkout($address)->assertUnprocessable();
        $this->assertSame(3, $products[0]->refresh()->stock_quantity);
        $this->assertDatabaseCount('orders', 0);
        config(['zalopay.key1' => null]);
        $this->checkout($address)->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_create_failure_preserves_attempt_and_retry_creates_new_without_stock_deduction(): void
    {
        $this->fakeGateway(['*/v2/create' => Http::response(['return_code' => 2, 'sub_return_code' => -401]), '*/v2/query' => Http::response(['return_code' => 2, 'sub_return_code' => -101, 'is_processing' => false])]);
        [$buyer,$order,$product] = $this->placed();
        $first = $order->payments()->first();
        $this->assertSame(PaymentStatus::FAILED, $first->status);
        $this->fakeGateway(['*/v2/create' => Http::response(['return_code' => 1, 'order_url' => 'https://sbgateway.zalopay.vn/pay']), '*/v2/query' => Http::response(['return_code' => 2, 'sub_return_code' => -101, 'is_processing' => false])]);
        $key = (string) Str::uuid();
        $this->retry($order, $key)->assertOk()->assertJsonCount(2, 'data.payments')->assertJsonPath('data.payments.1.status', 'PENDING');
        $this->retry($order, $key)->assertOk()->assertJsonCount(2, 'data.payments');
        $second = $order->payments()->latest('id')->first();
        $this->assertNotSame($first->transaction_reference, $second->transaction_reference);
        $this->assertSame(2, $second->attempt_number);
        $this->assertSame(PaymentStatus::FAILED, $first->refresh()->status);
        $this->assertSame(1, $product->refresh()->stock_quantity);
        $this->retry($order)->assertConflict();
        $this->assertDatabaseCount('payments', 2);
    }

    public function test_network_error_duplicate_create_or_unsafe_url_keeps_unresolved_payment_pending(): void
    {
        foreach ([Http::failedConnection(), Http::response(['return_code' => 2, 'sub_return_code' => -68]), Http::response(['return_code' => 1, 'order_url' => 'https://evil.test/pay'])] as $response) {
            $this->fakeGateway(['*/v2/create' => $response, '*/v2/query' => Http::response(['return_code' => 3, 'is_processing' => true])]);
            [$buyer,$order,$product] = $this->placed();
            $p = $order->payments()->first();
            $this->assertSame(PaymentStatus::PENDING, $p->status);
            $this->assertNull($p->order_url);
            $this->retry($order)->assertConflict();
            $this->assertSame(1, $product->refresh()->stock_quantity);
        }
    }

    public function test_valid_callback_is_idempotent_and_no_paid_order_retry_or_cancel(): void
    {
        [$buyer,$order,$product] = $this->placed();
        $p = $order->payments()->first();
        $data = $this->signed($p);
        $this->sendCallback($data);
        $audit = AuditLog::count();
        // ZaloPay may notify again: "already processed" (2), nothing written twice.
        $this->sendCallback($data, 2);
        $this->sendCallback($data, 2);
        $this->assertSame($audit, AuditLog::count());
        $this->assertSame(PaymentStatus::PAID, $p->refresh()->status);
        $this->assertNotNull($p->paid_at);
        $this->getJson('/api/v1/orders/'.$order->id)->assertOk()->assertJsonPath('data.payment_status', 'PAID')->assertJsonPath('data.amount_due', '0.00')->assertJsonPath('data.can_retry_payment', false)->assertJsonPath('data.payments.0.order_url', null);
        $this->retry($order)->assertConflict();
        $part = $order->shopOrders()->first();
        $this->postJson('/api/v1/orders/'.$order->id.'/shop-orders/'.$part->id.'/cancel', ['expected_status' => 'PENDING', 'reason' => 'Changed plans'])->assertConflict();
        $this->assertSame(1, $product->refresh()->stock_quantity);
    }

    public function test_invalid_signature_tampered_callback_and_wrong_identity_do_not_mark_paid(): void
    {
        [$buyer,$order] = $this->placed();
        $p = $order->payments()->first();
        $valid = $this->signed($p);
        $this->sendCallback([...$valid, 'mac' => str_repeat('0', 64)], -1);
        $this->sendCallback([...$valid, 'data' => str_replace('250000', '250001', $valid['data'])], -1);
        foreach ([['amount' => 250001], ['app_id' => 987], ['app_user' => 'buyer-0'], ['app_time' => 1], ['app_trans_id' => 'unknown'], ['zp_trans_id' => 'bad'], ['currency' => 'USD']] as $change) {
            $this->sendCallback($this->signed($p, $change), -1);
        }
        // Malformed notifications are still answered with HTTP 200 and -1, as ZaloPay expects.
        foreach ([[...$valid, 'type' => 2], ['data' => $valid['data']], [...$valid, 'mac' => 'not-hex'], [...$valid, 'extra' => 'x'], []] as $body) {
            $this->postJson('/api/v1/payments/zalopay/callback', $body)->assertOk()->assertExactJson(['return_code' => -1, 'return_message' => 'Invalid payment']);
        }
        $this->assertSame(PaymentStatus::PENDING, $p->refresh()->status);
        $this->assertNull($p->gateway_transaction_id);
    }

    public function test_replaying_transaction_for_a_different_order_cannot_pay_twice(): void
    {
        [$buyer,$order] = $this->placed();
        $this->sendCallback($this->signed($order->payments()->first()));
        [$other,$second] = $this->placed();
        $this->sendCallback($this->signed($second->payments()->first()), -1);
        $this->assertSame(PaymentStatus::PENDING, $second->payments()->first()->status);
    }

    public function test_query_success_verifies_db_amount_and_ref_and_signs_server_request(): void
    {
        [$buyer,$order] = $this->placed();
        $p = $order->payments()->first();
        foreach ([['amount' => 1], ['amount' => 250000, 'app_trans_id' => 'unknown'], ['amount' => 250000, 'zp_trans_id' => null]] as $extra) {
            $this->fakeGateway(['*/v2/query' => Http::response(['return_code' => 1, 'zp_trans_id' => 234567, ...$extra])]);
            $this->syncPayment($order)->assertOk()->assertJsonPath('data.payment_status', 'PENDING');
        }
        $this->fakeGateway(['*/v2/query' => Http::response(['return_code' => 1, 'amount' => 250000, 'zp_trans_id' => 234567, 'is_processing' => false])]);
        $this->syncPayment($order)->assertOk()->assertJsonPath('data.payment_status', 'PAID');
        Http::assertSent(fn ($request): bool => $request->url() === config('zalopay.query_url') && $request['app_trans_id'] === $p->transaction_reference && $request['mac'] === hash_hmac('sha256', $p->gateway_app_id.'|'.$p->transaction_reference.'|'.config('zalopay.key1'), config('zalopay.key1')));
        $this->sendCallback($this->signed($p, ['zp_trans_id' => 234567]), 2);
    }

    public function test_query_processing_insufficient_funds_and_failure_are_not_false_terminal_results(): void
    {
        [$buyer,$order] = $this->placed();
        foreach ([['return_code' => 3, 'is_processing' => true], ['return_code' => 2, 'sub_return_code' => -63, 'is_processing' => false], ['return_code' => 2, 'sub_return_code' => -500, 'is_processing' => false], ['return_code' => 2, 'sub_return_code' => -101, 'is_processing' => false], []] as $result) {
            $this->fakeGateway(['*/v2/query' => Http::response($result)]);
            $this->syncPayment($order)->assertOk()->assertJsonPath('data.payment_status', 'PENDING');
            $this->retry($order)->assertConflict();
        }
        $this->fakeGateway(['*/v2/query' => Http::failedConnection()]);
        $this->syncPayment($order)->assertOk()->assertJsonPath('data.payment_status', 'PENDING');
    }

    public function test_confirmed_expiry_allows_retry_but_does_not_release_or_deduct_stock(): void
    {
        [$buyer,$order,$product] = $this->placed();
        $p = $order->payments()->first();
        $p->update(['expires_at' => now()->subMinutes(2)]);
        $this->fakeGateway(['*/v2/query' => Http::response(['return_code' => 2, 'sub_return_code' => -54, 'is_processing' => false]), '*/v2/create' => Http::response(['return_code' => 1, 'order_url' => 'https://sbgateway.zalopay.vn/pay'])]);
        $this->syncPayment($order)->assertOk()->assertJsonPath('data.payment_status', 'FAILED')->assertJsonPath('data.can_retry_payment', true);
        $this->retry($order)->assertOk()->assertJsonCount(2, 'data.payments');
        $this->assertSame(1, $product->refresh()->stock_quantity);
        $this->sendCallback($this->signed($p));
        $this->assertTrue($p->refresh()->reconciliation_required);
        $this->assertSame(PaymentStatus::FAILED, $p->status);
        $this->assertSame(PaymentStatus::CANCELLED, $order->payments()->latest('id')->first()->status);
        $this->retry($order)->assertConflict();
        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'payments', 'entity_id' => $order->payments()->latest('id')->value('id'), 'action' => 'payment.cancelled', 'from_status' => 'PENDING', 'to_status' => 'CANCELLED']);
    }

    public function test_cancellation_restores_stock_once_and_late_callback_only_flags_reconciliation(): void
    {
        [$buyer,$order,$product] = $this->placed();
        $p = $order->payments()->first();
        $part = $order->shopOrders()->first();
        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/api/v1/orders/'.$order->id.'/shop-orders/'.$part->id.'/cancel', ['expected_status' => 'PENDING', 'reason' => 'Changed plans'])->assertOk();
        }
        $this->assertSame(3, $product->refresh()->stock_quantity);
        $this->assertSame(PaymentStatus::CANCELLED, $p->refresh()->status);
        $this->sendCallback($this->signed($p));
        $this->sendCallback($this->signed($p), 2);
        $this->assertTrue($p->refresh()->reconciliation_required);
        $this->assertSame(PaymentStatus::CANCELLED, $p->status);
        $this->assertSame(ShopOrderStatus::CANCELLED, $part->refresh()->status);
        $this->assertSame(3, $product->refresh()->stock_quantity);
        $this->retry($order)->assertConflict();
    }

    public function test_online_seller_fulfillment_requires_verified_payment_and_no_cod_collection(): void
    {
        [$buyer,$order] = $this->placed();
        $part = $order->shopOrders()->first();
        $seller = $part->shop->owner;
        $this->signIn($seller);
        $path = '/api/v1/seller/orders/'.$part->id.'/status';
        $this->patchJson($path, ['expected_status' => 'PENDING', 'status' => 'CONFIRMED'])->assertConflict();
        $this->sendCallback($this->signed($order->payments()->first()));
        foreach (['PENDING' => 'CONFIRMED', 'CONFIRMED' => 'PROCESSING', 'PROCESSING' => 'SHIPPING', 'SHIPPING' => 'COMPLETED'] as $from => $to) {
            $this->patchJson($path, ['expected_status' => $from, 'status' => $to])->assertOk()->assertJsonPath('data.status', $to);
        }
        $this->assertSame(PaymentStatus::PAID, $order->payments()->first()->status);
        $this->assertSame(1, $order->payments()->count());
    }

    public function test_buyer_ownership_strict_query_body_and_no_secrets_in_resources_or_audit(): void
    {
        [$buyer,$order] = $this->placed();
        $this->signIn(User::factory()->create());
        $this->retry($order)->assertForbidden();
        $this->syncPayment($order)->assertForbidden();
        $this->getJson('/api/v1/orders/'.$order->id.'/payments')->assertForbidden();
        $this->signIn($buyer);
        $this->postJson('/api/v1/orders/'.$order->id.'/payments/zalopay/query', ['status' => 'PAID'])->assertUnprocessable();
        $json = $this->getJson('/api/v1/orders/'.$order->id)->assertOk()->getContent().AuditLog::all()->toJson();
        foreach ([config('zalopay.key1'), config('zalopay.key2'), '"mac"', 'card_number', 'gateway_transaction_id', 'transaction_reference'] as $secret) {
            $this->assertStringNotContainsString($secret, $json);
        }
        auth()->forgetGuards();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/orders/'.$order->id.'/payments')->assertUnauthorized();
    }

    public function test_gateway_url_allowlist_and_production_sandbox_guard(): void
    {
        foreach (['javascript:alert(1)', 'https://zalopay.vn.evil.test', 'https://evil.test', 'https://user@zalopay.vn', 'http://sbgateway.zalopay.vn', 'https://sbgateway.zalopay.vn:1234'] as $url) {
            $this->assertFalse(ZaloPayGateway::safeUrl($url));
        }
        $this->assertTrue(ZaloPayGateway::safeUrl('https://sbgateway.zalopay.vn/openinapp?token=x'));
        $this->app['env'] = 'production';
        $this->expectException(HttpException::class);
        app(ZaloPayGateway::class)->configured();
    }

    public function test_reconciler_queries_long_lived_attempts_until_seven_days_after_expiry(): void
    {
        [$buyer,$order] = $this->placed();
        $payment = $order->payments()->first();
        $payment->update(['created_at' => now()->subDays(10), 'expires_at' => now()->addDays(10)]);
        $this->fakeGateway(['*/v2/query' => Http::response(['return_code' => 1, 'amount' => 250000, 'zp_trans_id' => 987654321, 'is_processing' => false])]);
        $this->artisan('zalopay:reconcile', ['--limit' => 1])->assertSuccessful();
        $this->assertSame(PaymentStatus::PAID, $payment->refresh()->status);
    }

    public function test_online_order_overview_does_not_load_payment_attempts_for_admin_statistics(): void
    {
        [$buyer,$order] = $this->placed();
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $this->signIn($admin);
        DB::enableQueryLog();
        $this->getJson('/api/v1/admin/orders/'.$order->id)->assertOk()->assertJsonPath('data.overall_status', 'PENDING')->assertJsonMissingPath('data.payments');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        foreach ($queries as $query) {
            $this->assertStringNotContainsString('`payments`', strtolower($query['query']));
        }
    }

    public function test_callback_is_public_answers_200_logs_without_secrets_and_rejects_a_wrong_amount(): void
    {
        [$buyer,$order] = $this->placed();
        $p = $order->payments()->first();
        Log::spy();
        // A server-to-server call: no session, no Origin, no CSRF token.
        $this->app['session']->flush();
        Auth::forgetGuards();
        $this->withHeaders(['Origin' => ''])->withoutHeader('Origin');
        // Valid MAC but an amount that differs from the payment: rejected, nothing recorded.
        $wrong = $this->signed($p, ['amount' => 250000 + 1000]);
        $this->postJson('/api/v1/payments/zalopay/callback', $wrong)->assertOk()->assertJsonPath('return_code', -1);
        $this->assertSame(PaymentStatus::PENDING, $p->refresh()->status);
        $this->assertNull($p->gateway_transaction_id);
        $valid = $this->signed($p);
        $this->postJson('/api/v1/payments/zalopay/callback', $valid)->assertOk()->assertExactJson(['return_code' => 1, 'return_message' => 'Accepted']);
        $this->postJson('/api/v1/payments/zalopay/callback', $valid)->assertOk()->assertExactJson(['return_code' => 2, 'return_message' => 'Already processed']);
        $this->assertSame(PaymentStatus::PAID, $p->refresh()->status);
        // One log line per notification (-1, 1, 2): references and an 8-character MAC prefix, never a key or a full MAC.
        foreach ([['warning', -1, $wrong], ['info', 1, $valid], ['info', 2, $valid]] as [$level, $result, $body]) {
            Log::shouldHaveReceived('log')->withArgs(function (string $logged, string $message, array $context) use ($level, $result, $body, $p): bool {
                $flat = json_encode($context);

                return $logged === $level && $message === 'zalopay.callback' && $context['result'] === $result
                    && $context['app_trans_id'] === $p->transaction_reference && $context['mac_prefix'] === substr($body['mac'], 0, 8)
                    && ! str_contains($flat, $body['mac']) && ! str_contains($flat, (string) config('zalopay.key1')) && ! str_contains($flat, (string) config('zalopay.key2'));
            })->once();
        }
    }

    public function test_create_sends_the_configured_redirect_and_callback_urls(): void
    {
        config(['zalopay.callback_url' => 'https://demo-tunnel.trycloudflare.com/api/v1/payments/zalopay/callback']);
        [$buyer,$address] = $this->cart();
        $id = $this->checkout($address)->assertCreated()->json('data.id');
        Http::assertSent(function ($request) use ($id): bool {
            $data = $request->data();

            return str_ends_with($request->url(), '/v2/create')
                && json_decode($data['embed_data'], true)['redirecturl'] === 'http://localhost:5173/payment/result?order='.$id
                && $data['callback_url'] === 'https://demo-tunnel.trycloudflare.com/api/v1/payments/zalopay/callback'
                && $data['bank_code'] === ''
                && preg_match('/^\d{6}_[0-9a-f]{32}$/', $data['app_trans_id']) === 1 && strlen($data['app_trans_id']) <= 40;
        });
    }

    public function test_callback_and_redirect_urls_default_from_app_and_frontend_urls(): void
    {
        $load = function (array $env): array {
            foreach ($env as $key => $value) {
                putenv($value === null ? $key : "$key=$value");
                $_ENV[$key] = $_SERVER[$key] = $value;
                if ($value === null) {
                    unset($_ENV[$key], $_SERVER[$key]);
                }
            }

            return require config_path('zalopay.php');
        };
        $keys = ['ZALOPAY_CALLBACK_URL', 'ZALOPAY_REDIRECT_URL', 'ZALOPAY_RETURN_URL', 'APP_URL', 'FRONTEND_URLS'];
        $saved = array_combine($keys, array_map(fn ($key) => $_ENV[$key] ?? null, $keys));
        try {
            $config = $load(['ZALOPAY_CALLBACK_URL' => '', 'ZALOPAY_REDIRECT_URL' => '', 'ZALOPAY_RETURN_URL' => '', 'APP_URL' => 'http://127.0.0.1:8000/', 'FRONTEND_URLS' => 'http://localhost:5173,http://127.0.0.1:5173']);
            $this->assertSame('http://127.0.0.1:8000/api/v1/payments/zalopay/callback', $config['callback_url']);
            $this->assertSame('http://localhost:5173/payment/result', $config['redirect_url']);
            $config = $load(['ZALOPAY_CALLBACK_URL' => 'https://x.trycloudflare.com/api/v1/payments/zalopay/callback', 'ZALOPAY_REDIRECT_URL' => 'https://shop.test/payment/result']);
            $this->assertSame('https://x.trycloudflare.com/api/v1/payments/zalopay/callback', $config['callback_url']);
            $this->assertSame('https://shop.test/payment/result', $config['redirect_url']);
        } finally {
            $load($saved);
        }
    }
}
