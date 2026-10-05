<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ShopOrderStatus;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ShopOrder;
use App\Models\User;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class CheckoutConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    protected function tearDown(): void
    {
        // Remove this suite's online fixtures before DatabaseMigrations rolls back
        // the forward migration, whose production guard preserves payment history.
        if ($this->app && Schema::hasTable('orders')) {
            $this->assertSame('secondhand_marketplace_test', DB::selectOne('SELECT DATABASE() name')->name);
            $ids = Order::where('payment_method', 'ZALOPAY')->pluck('id');
            $parts = ShopOrder::whereIn('order_id', $ids)->pluck('id');
            Payment::whereIn('order_id', $ids)->delete();
            OrderItem::whereIn('shop_order_id', $parts)->delete();
            ShopOrder::whereIn('id', $parts)->delete();
            Order::whereIn('id', $ids)->delete();
        }
        parent::tearDown();
    }

    /** Separate committed fixtures and PHP processes exercise real MySQL row locks. */
    private function race(array $jobs): array
    {
        $dir = storage_path('framework/testing');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $prefix = $dir.'/checkout-'.Str::uuid();
        $go = $prefix.'-go';
        $processes = [];
        $paths = [$go];
        try {
            foreach ($jobs as $i => $job) {
                $ready = $prefix.'-'.$i;
                $paths[] = $ready;
                $process = new Process([PHP_BINARY, base_path('tests/Support/checkout-worker.php'), json_encode([...$job, 'ready' => $ready, 'go' => $go], JSON_THROW_ON_ERROR)], base_path(), ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'secondhand_marketplace_test', 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'], null, 35);
                $process->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 20;
            while (count(array_filter(array_slice($paths, 1), 'is_file')) !== count($jobs)) {
                foreach ($processes as $process) {
                    if (! $process->isRunning()) {
                        $this->fail('Worker exited before barrier: '.$process->getErrorOutput());
                    }
                }
                if (microtime(true) > $deadline) {
                    $this->fail('Workers did not reach barrier');
                } usleep(10000);
            }
            file_put_contents($go, 'go');
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
                try {
                    $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    $this->fail('Worker returned invalid JSON: '.$process->getOutput());
                }
            }

            return $results;
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            } foreach ($paths as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    private function buyer(Product $product): array
    {
        $buyer = User::factory()->create();
        $address = Address::factory()->for($buyer, 'user')->create();
        $cart = Cart::factory()->for($buyer, 'user')->create();
        CartItem::factory()->for($cart)->for($product)->create(['quantity' => 1]);

        return ['action' => 'checkout', 'user' => $buyer->id, 'address' => $address->id, 'key' => (string) Str::uuid()];
    }

    public function test_real_mysql_competing_checkout_replay_cancellation_and_cod_completion(): void
    {
        $product = Product::factory()->active()->create(['stock_quantity' => 1]);
        $a = $this->buyer($product);
        $b = $this->buyer($product);
        $results = $this->race([$a, $b]);
        $statuses = array_column($results, 'status');
        sort($statuses);
        $this->assertSame([201, 409], $statuses);
        $this->assertSame(0, $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('cart_items', 1);
        $order = Order::firstOrFail();
        $part = $order->shopOrders()->first();
        $cancel = ['action' => 'cancel', 'user' => $order->buyer_id, 'part' => $part->id, 'transition' => ['status' => 'CANCELLED', 'expected_status' => 'PENDING', 'reason' => 'Changed plans']];
        $results = $this->race([$cancel, $cancel]);
        $this->assertSame([200, 200], array_column($results, 'status'));
        $this->assertSame(1, $product->refresh()->stock_quantity);
        $this->assertSame(ShopOrderStatus::CANCELLED, $part->refresh()->status);
        $this->assertSame(PaymentStatus::CANCELLED, $order->payments()->first()->status);
        $job = $this->buyer(Product::factory()->active()->create(['stock_quantity' => 1]));
        $results = $this->race([$job, $job]);
        $statuses = array_column($results, 'status');
        sort($statuses);
        $this->assertSame([200, 201], $statuses);
        $this->assertSame($results[0]['order'], $results[1]['order']);
        $first = Product::factory()->active()->create(['stock_quantity' => 1]);
        $job = $this->buyer($first);
        $second = Product::factory()->active()->create(['stock_quantity' => 1]);
        CartItem::factory()->create(['cart_id' => Cart::where('user_id', $job['user'])->value('id'), 'product_id' => $second->id, 'quantity' => 1]);
        $order = app(CheckoutService::class)->checkout(User::findOrFail($job['user']), $job['address'], $job['key'])['order'];
        $jobs = [];
        foreach ($order->shopOrders as $part) {
            $part->update(['status' => ShopOrderStatus::SHIPPING, 'confirmed_at' => now(), 'processing_at' => now(), 'shipping_at' => now()]);
            $jobs[] = ['action' => 'transition', 'user' => $part->shop->owner_id, 'part' => $part->id, 'transition' => ['expected_status' => 'SHIPPING', 'status' => 'COMPLETED', 'cod_collected' => true]];
        }
        $this->assertSame([200, 200], array_column($this->race($jobs), 'status'));
        $this->assertSame(PaymentStatus::PAID, $order->payments()->first()->status);
        $this->assertSame(1, $order->payments()->count());
    }

    public function test_real_mysql_zalopay_callback_duplicates_and_cancel_race_preserve_inventory(): void
    {
        $key = bin2hex(random_bytes(32));
        config(['zalopay.app_id' => '123456', 'zalopay.key1' => $key, 'zalopay.key2' => $key, 'zalopay.expire_seconds' => 900]);
        foreach ([false, true] as $cancelRace) {
            $product = Product::factory()->active()->create(['stock_quantity' => 1, 'price' => '250000.00']);
            $job = $this->buyer($product);
            $order = app(CheckoutService::class)->checkout(User::findOrFail($job['user']), $job['address'], $job['key'], PaymentMethod::ZALOPAY)['order'];
            $payment = $order->payments->first();
            $payment->update(['gateway_requested_at' => now()]);
            $raw = json_encode(['app_id' => $payment->gateway_app_id, 'app_user' => 'buyer-'.$order->buyer_id, 'app_time' => $payment->gateway_app_time, 'app_trans_id' => $payment->transaction_reference, 'amount' => 250000, 'zp_trans_id' => 100000 + $payment->id], JSON_THROW_ON_ERROR);
            $callback = ['action' => 'zalopay_callback', 'user' => $order->buyer_id, 'key2' => $key, 'callback' => ['data' => $raw, 'mac' => hash_hmac('sha256', $raw, $key), 'type' => 1]];
            $part = $order->shopOrders->first();
            $cancel = ['action' => 'cancel', 'user' => $order->buyer_id, 'part' => $part->id, 'transition' => ['expected_status' => 'PENDING', 'status' => 'CANCELLED', 'reason' => 'Race verification']];
            $results = $this->race([$callback, $cancelRace ? $cancel : $callback]);
            $this->assertSame(1, $results[0]['status']);
            $payment->refresh();
            $part->refresh();
            if ($payment->status === PaymentStatus::PAID) {
                $this->assertSame(0, $product->refresh()->stock_quantity);
                $this->assertSame(ShopOrderStatus::PENDING, $part->status);
                $this->assertNull($part->stock_restored_at);
                $this->assertSame($cancelRace ? 409 : 1, $results[1]['status']);
            } else {
                $this->assertTrue($cancelRace);
                $this->assertSame(200, $results[1]['status']);
                $this->assertSame(PaymentStatus::CANCELLED, $payment->status);
                $this->assertTrue($payment->reconciliation_required);
                $this->assertSame(1, $product->refresh()->stock_quantity);
                $this->assertNotNull($part->stock_restored_at);
            }
            $this->assertSame(1, $order->payments()->count());
        }
    }

    public function test_real_mysql_simultaneous_zalopay_retries_create_one_attempt_without_stock_change(): void
    {
        $key = bin2hex(random_bytes(32));
        config(['zalopay.app_id' => '123456', 'zalopay.key1' => $key, 'zalopay.key2' => $key, 'zalopay.expire_seconds' => 900]);
        $product = Product::factory()->active()->create(['stock_quantity' => 1, 'price' => '250000.00']);
        $job = $this->buyer($product);
        $order = app(CheckoutService::class)->checkout(User::findOrFail($job['user']), $job['address'], $job['key'], PaymentMethod::ZALOPAY)['order'];
        $order->payments->first()->update(['status' => PaymentStatus::FAILED, 'failure_code' => 'CREATE_REJECTED']);
        $retry = ['action' => 'zalopay_retry', 'order' => $order->id, 'user' => $order->buyer_id, 'key2' => $key, 'key' => (string) Str::uuid()];
        $results = $this->race([$retry, $retry]);
        $this->assertSame([200, 200], array_column($results, 'status'));
        $this->assertSame($results[0]['payment'], $results[1]['payment']);
        $this->assertSame(2, $order->payments()->count());
        $this->assertSame(1, $order->payments()->where('status', 'PENDING')->count());
        $this->assertSame(0, $product->refresh()->stock_quantity);
    }

    public function test_real_mysql_default_address_creation_switching_deletion_and_foreign_access(): void
    {
        $buyer = User::factory()->create();
        $create = ['action' => 'address_create', 'user' => $buyer->id, 'address_data' => ['recipient_name' => 'Race Buyer', 'phone' => '0901234567', 'address_line' => '10 Race Street', 'ward' => 'Ward 1', 'province' => 'Ho Chi Minh', 'country_code' => 'VN', 'is_default' => true]];
        $this->assertSame([200, 200], array_column($this->race([$create, $create]), 'status'));
        $this->assertSame(2, $buyer->addresses()->count());
        $this->assertSame(1, $buyer->addresses()->where('is_default', true)->count());
        $ids = $buyer->addresses()->orderBy('id')->pluck('id');
        $first = ['action' => 'address_default', 'user' => $buyer->id, 'address' => $ids[0]];
        $second = [...$first, 'address' => $ids[1]];
        $this->assertSame([200, 200], array_column($this->race([$first, $second]), 'status'));
        $this->assertSame(1, $buyer->addresses()->where('is_default', true)->count());
        $foreign = Address::factory()->create(['is_default' => true]);
        $this->assertSame([200, 403], array_column($this->race([$second, [...$first, 'address' => $foreign->id]]), 'status'));
        $this->assertTrue($foreign->refresh()->is_default);
        $results = $this->race([[...$first, 'action' => 'address_delete'], $first]);
        $this->assertSame(200, $results[0]['status']);
        $this->assertContains($results[1]['status'], [200, 404]);
        $this->assertSame(1, $buyer->addresses()->count());
        $this->assertSame($ids[1], $buyer->addresses()->where('is_default', true)->value('id'));
    }

    public function test_real_mysql_duplicate_review_creation_is_serialized_and_foreign_buyer_is_rejected(): void
    {
        $item = OrderItem::factory()->for(ShopOrder::factory()->completed())->create();
        $job = ['action' => 'review_create', 'user' => $item->shopOrder->order->buyer_id, 'item' => $item->id];
        $statuses = array_column($this->race([$job, $job]), 'status');
        sort($statuses);
        $this->assertSame([200, 409], $statuses);
        $foreign = User::factory()->create();
        $this->assertSame([409, 403], array_column($this->race([$job, [...$job, 'user' => $foreign->id]]), 'status'));
        $this->assertSame(1, $item->review()->count());
        $this->assertSame($job['user'], $item->review()->firstOrFail()->user_id);
    }
}
