<?php

namespace Tests\Feature;

use App\Enums\AuctionStatus;
use App\Models\Address;
use App\Models\Auction;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use App\Services\AuctionNotificationService;
use App\Services\AuctionService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class AuctionConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    /** Separate committed fixtures and PHP processes exercise real MySQL row locks. */
    private function race(array $jobs): array
    {
        $dir = storage_path('framework/testing');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $prefix = $dir.'/auction-'.Str::uuid();
        $go = $prefix.'-go';
        $processes = [];
        $paths = [$go];
        try {
            foreach ($jobs as $i => $job) {
                $ready = $prefix.'-'.$i;
                $paths[] = $ready;
                $process = new Process([PHP_BINARY, base_path('tests/Support/auction-worker.php'), json_encode([...$job, 'ready' => $ready, 'go' => $go], JSON_THROW_ON_ERROR)], base_path(), ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'secondhand_marketplace_test', 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'], null, 35);
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

    public function test_real_mysql_concurrent_equal_bids_are_revalidated_after_lock(): void
    {
        $auction = Auction::factory()->create();
        $users = User::factory()->count(2)->create();
        foreach (['100.00', '110.00'] as $amount) {
            $jobs = $users->map(fn (User $user) => ['action' => 'bid', 'user' => $user->id, 'auction' => $auction->id, 'amount' => $amount])->all();
            $statuses = array_column($this->race($jobs), 'status');
            sort($statuses);
            $this->assertSame([201, 422], $statuses);
            $this->assertSame($amount, $auction->refresh()->current_price);
        }
        $this->assertSame(2, $auction->bids()->count());
    }

    public function test_real_mysql_duplicate_creation_has_only_one_unfinished_auction(): void
    {
        $product = Product::factory()->active()->create();
        $job = ['action' => 'create', 'user' => $product->shop->owner_id, 'data' => ['product_id' => $product->id, 'start_price' => '100.00', 'bid_increment' => '10.00', 'starts_at' => now()->subMinute()->toISOString(), 'ends_at' => now()->addHour()->toISOString()]];
        $statuses = array_column($this->race([$job, $job]), 'status');
        sort($statuses);
        $this->assertSame([201, 409], $statuses);
        $this->assertDatabaseCount('auctions', 1);
    }

    public function test_real_mysql_bid_and_cancel_race_preserves_a_valid_outcome(): void
    {
        $auction = Auction::factory()->create();
        $buyer = User::factory()->create();
        $jobs = [['action' => 'bid', 'user' => $buyer->id, 'auction' => $auction->id, 'amount' => '100.00'], ['action' => 'cancel', 'user' => $auction->seller_id, 'auction' => $auction->id]];
        $statuses = array_column($this->race($jobs), 'status');
        $auction->refresh();
        if ($auction->status === AuctionStatus::CANCELLED) {
            $this->assertSame([409, 200], $statuses);
            $this->assertSame(0, $auction->bids()->count());
        } else {
            $this->assertSame([201, 409], $statuses);
            $this->assertSame(AuctionStatus::LIVE, $auction->status);
            $this->assertSame(1, $auction->bids()->count());
        }
    }

    public function test_real_mysql_admin_cancel_racing_bids_never_leaves_a_half_state(): void
    {
        $admin = User::factory()->admin()->create();
        $cancelledFirst = $bidFirst = 0;
        for ($round = 0; $round < 4; $round++) {
            $auction = Auction::factory()->create();
            [$a, $b] = User::factory()->count(2)->create();
            $jobs = [
                ['action' => 'bid', 'user' => $a->id, 'auction' => $auction->id, 'amount' => '100.00'],
                ['action' => 'admin-cancel', 'user' => $admin->id, 'auction' => $auction->id, 'reason' => 'Vi phạm chính sách', 'expected_status' => 'LIVE'],
                ['action' => 'bid', 'user' => $b->id, 'auction' => $auction->id, 'amount' => '110.00'],
            ];
            $statuses = array_column($this->race($jobs), 'status');
            $auction->refresh();
            // The Admin always wins eventually; each bid either committed before the cancellation or was refused.
            $this->assertSame(200, $statuses[1]);
            $this->assertSame(AuctionStatus::CANCELLED, $auction->status);
            $this->assertNull($auction->active_product_id);
            $accepted = count(array_filter([$statuses[0], $statuses[2]], fn (int $status): bool => $status === 201));
            $this->assertSame($accepted, $auction->bids()->count());
            foreach ([$statuses[0], $statuses[2]] as $status) {
                $this->assertContains($status, [201, 409, 422]);
            }
            $this->assertSame($accepted === 0 ? '100.00' : (string) $auction->bids()->max('amount'), $auction->current_price);
            // Every bidder whose bid was stored is told exactly once; refused bidders are not.
            foreach ([[$a, $statuses[0]], [$b, $statuses[2]]] as [$bidder, $status]) {
                $this->assertSame($status === 201 ? 1 : 0, $bidder->notifications()->where('type', 'AUCTION_CANCELLED')->count());
            }
            $this->assertSame(1, User::findOrFail($auction->seller_id)->notifications()->where('type', 'AUCTION_CANCELLED')->count());
            $accepted === 0 ? $cancelledFirst++ : $bidFirst++;
        }
        $this->assertSame(4, $cancelledFirst + $bidFirst);
    }

    public function test_real_mysql_finalization_retries_choose_the_same_winner(): void
    {
        $auction = Auction::factory()->create();
        $bid = app(AuctionService::class)->placeBid(User::factory()->create(), $auction, '100.00');
        $auction->forceFill(['ends_at' => now()->subSecond()])->save();
        $job = ['action' => 'finalize', 'auction' => $auction->id];
        $results = $this->race([$job, $job]);
        $this->assertSame([200, 200], array_column($results, 'status'));
        $this->assertSame([$bid->id, $bid->id], array_column($results, 'winner'));
        $this->assertSame(AuctionStatus::ENDED, $auction->refresh()->status);
        $this->assertSame($bid->user_id, $auction->winner_user_id);
    }

    private function endedWinner(): array
    {
        $auction = Auction::factory()->create();
        $auction->product->update(['stock_quantity' => 1]);
        $winner = User::factory()->create();
        $address = Address::factory()->for($winner, 'user')->create();
        app(AuctionService::class)->placeBid($winner, $auction, '120.00');
        $auction->forceFill(['ends_at' => now()->subSecond()])->save();

        return [app(AuctionService::class)->finalize($auction), $winner, $address];
    }

    public function test_real_mysql_parallel_winner_checkouts_create_one_order_and_decrement_once(): void
    {
        [$auction, $winner, $address] = $this->endedWinner();
        $job = ['action' => 'winner-checkout', 'user' => $winner->id, 'auction' => $auction->id, 'address' => $address->id, 'key' => (string) Str::uuid()];
        $results = $this->race([$job, [...$job, 'key' => (string) Str::uuid()]]);
        $statuses = array_column($results, 'status');
        sort($statuses);
        $this->assertSame([200, 201], $statuses);
        $this->assertSame($results[0]['order'], $results[1]['order']);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertSame(0, $auction->product->refresh()->stock_quantity);
    }

    public function test_real_mysql_fixed_checkout_racing_winner_cannot_claim_auction_unit(): void
    {
        [$auction, $winner, $address] = $this->endedWinner();
        $other = User::factory()->create();
        $otherAddress = Address::factory()->for($other, 'user')->create();
        $cart = Cart::factory()->for($other, 'user')->create();
        CartItem::factory()->for($cart)->for($auction->product)->create(['quantity' => 1]);
        $results = $this->race([
            ['action' => 'winner-checkout', 'user' => $winner->id, 'auction' => $auction->id, 'address' => $address->id, 'key' => (string) Str::uuid()],
            ['action' => 'fixed-checkout', 'user' => $other->id, 'address' => $otherAddress->id, 'key' => (string) Str::uuid()],
        ]);
        $this->assertSame([201, 409], array_column($results, 'status'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertSame(0, $auction->product->refresh()->stock_quantity);
    }

    public function test_real_mysql_double_finalize_persists_one_winner_notification(): void
    {
        [$auction, $winner] = $this->endedWinner();
        $job = ['action' => 'finalize', 'auction' => $auction->id];
        $this->assertSame([200, 200], array_column($this->race([$job, $job]), 'status'));
        $this->assertSame(1, $winner->notifications()->where('type', 'AUCTION_WON')->count());
        $this->assertSame(1, $auction->seller->notifications()->where('type', 'AUCTION_ENDED')->count());
    }

    public function test_real_mysql_bid_near_end_racing_finalize_has_one_authoritative_winner(): void
    {
        $auction = Auction::factory()->create();
        $a = User::factory()->create();
        $b = User::factory()->create();
        app(AuctionService::class)->placeBid($a, $auction, '100.00');
        $results = $this->race([
            ['action' => 'bid', 'auction' => $auction->id, 'user' => $b->id, 'amount' => '110.00', 'at' => $auction->ends_at->subMillisecond()->toISOString()],
            ['action' => 'finalize', 'auction' => $auction->id, 'at' => $auction->ends_at->addMillisecond()->toISOString()],
        ]);
        $auction->refresh();
        $this->assertContains($results[0]['status'], [201, 409]);
        $this->assertSame(200, $results[1]['status']);
        $this->assertSame($results[0]['status'] === 201 ? $b->id : $a->id, $auction->winner_user_id);
        $this->assertSame(1, $auction->winner->notifications()->where('type', 'AUCTION_WON')->count());
    }

    public function test_real_mysql_expiry_boundary_racing_checkout_does_not_reserve_stock(): void
    {
        [$auction, $winner, $address] = $this->endedWinner();
        $at = $auction->winner_checkout_expires_at->toISOString();
        $results = $this->race([
            ['action' => 'winner-checkout', 'auction' => $auction->id, 'user' => $winner->id, 'address' => $address->id, 'key' => (string) Str::uuid(), 'at' => $at],
            ['action' => 'remind', 'auction' => $auction->id, 'user' => $winner->id, 'at' => $at],
        ]);
        $this->assertSame([409, 200], array_column($results, 'status'));
        $this->assertFalse($results[1]['created']);
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(1, $auction->product->refresh()->stock_quantity);
    }

    public function test_real_mysql_reminder_racing_checkout_never_sends_after_order_commit(): void
    {
        [$auction, $winner, $address] = $this->endedWinner();
        $auction->forceFill(['winner_checkout_expires_at' => now()->addMinutes(20)])->save();
        $results = $this->race([
            ['action' => 'winner-checkout', 'auction' => $auction->id, 'user' => $winner->id, 'address' => $address->id, 'key' => (string) Str::uuid()],
            ['action' => 'remind', 'auction' => $auction->id, 'user' => $winner->id],
        ]);
        $this->assertSame([201, 200], array_column($results, 'status'));
        $this->assertSame($results[1]['created'] ? 1 : 0, $winner->notifications()->where('type', 'WINNER_CHECKOUT_EXPIRING')->count());
        $this->assertFalse(app(AuctionNotificationService::class)->remind($auction));
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(0, $auction->product->refresh()->stock_quantity);
    }

    public function test_real_mysql_two_reminder_workers_create_one_notification(): void
    {
        [$auction, $winner] = $this->endedWinner();
        $auction->forceFill(['winner_checkout_expires_at' => now()->addMinutes(20)])->save();
        $job = ['action' => 'remind', 'auction' => $auction->id, 'user' => $winner->id];
        $results = $this->race([$job, $job]);
        $this->assertSame(1, count(array_filter(array_column($results, 'created'))));
        $this->assertSame(1, $winner->notifications()->where('type', 'WINNER_CHECKOUT_EXPIRING')->count());
    }

    public function test_real_mysql_cancel_racing_scheduled_activation_cannot_resurrect_auction(): void
    {
        $auction = Auction::factory()->create(['status' => AuctionStatus::SCHEDULED, 'starts_at' => now()->subMinute()]);
        $results = $this->race([
            ['action' => 'start', 'auction' => $auction->id, 'user' => $auction->seller_id],
            ['action' => 'cancel', 'auction' => $auction->id, 'user' => $auction->seller_id],
        ]);
        $this->assertSame([200, 200], array_column($results, 'status'));
        $this->assertSame(AuctionStatus::CANCELLED, $auction->refresh()->status);
        $this->assertSame(1, $auction->seller->notifications()->where('type', 'AUCTION_CANCELLED')->count());
    }
}
