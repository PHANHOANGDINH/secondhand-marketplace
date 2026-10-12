<?php

namespace Tests\Feature;

use App\Enums\AuctionStatus;
use App\Enums\CategoryStatus;
use App\Enums\ProductStatus;
use App\Enums\ShopStatus;
use App\Enums\UserStatus;
use App\Models\Auction;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\AuctionService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AuctionTest extends TestCase
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

    private function payload(Product $product): array
    {
        return ['product_id' => $product->id, 'start_price' => '100.00', 'bid_increment' => '10.00', 'starts_at' => now()->subMinute()->toISOString(), 'ends_at' => now()->addHour()->toISOString()];
    }

    public function test_owner_creates_live_or_scheduled_auction_without_changing_fixed_price_product(): void
    {
        $product = Product::factory()->active()->create();
        $original = $product->refresh()->getRawOriginal();
        $this->login($product->shop->owner);
        $id = $this->postJson('/api/v1/seller/auctions', $this->payload($product))->assertCreated()->assertJsonPath('data.status', 'LIVE')->assertJsonPath('data.current_price', '100.00')->assertJsonStructure(['data' => ['server_time']])->json('data.id');
        $this->getJson('/api/v1/seller/auctions/'.$id)->assertOk();
        $this->getJson('/api/v1/seller/auctions')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson('/api/v1/seller/auctions', $this->payload($product))->assertConflict();
        $this->postJson('/api/v1/seller/auctions/'.$id.'/cancel')->assertOk();
        $this->postJson('/api/v1/seller/auctions', [...$this->payload($product), 'starts_at' => now()->addMinutes(5)->toISOString()])->assertCreated()->assertJsonPath('data.status', 'SCHEDULED');
        $this->assertSame($original, $product->refresh()->getRawOriginal());
    }

    public function test_first_higher_and_exact_increment_bids_allow_same_user_to_bid_again(): void
    {
        $auction = Auction::factory()->create();
        $this->login(User::factory()->create());
        $this->postJson('/api/v1/auctions/'.$auction->id.'/bids', ['amount' => '100.00'])->assertCreated()->assertJsonPath('data.amount', '100.00');
        $this->postJson('/api/v1/auctions/'.$auction->id.'/bids', ['amount' => '110.00'])->assertCreated();
        $this->postJson('/api/v1/auctions/'.$auction->id.'/bids', ['amount' => '125.00'])->assertCreated();
        $this->assertSame('125.00', $auction->refresh()->current_price);
        $this->assertDatabaseCount('bids', 3);
    }

    public function test_below_minimum_first_and_later_bids_are_rejected_without_changing_price(): void
    {
        $auction = Auction::factory()->create();
        $this->login(User::factory()->create());
        $this->postJson('/api/v1/auctions/'.$auction->id.'/bids', ['amount' => '99.99'])->assertUnprocessable();
        $this->postJson('/api/v1/auctions/'.$auction->id.'/bids', ['amount' => '100.00'])->assertCreated();
        foreach (['100.00', '109.99'] as $amount) {
            $this->postJson('/api/v1/auctions/'.$auction->id.'/bids', ['amount' => $amount])->assertUnprocessable();
        }
        $this->assertSame('100.00', $auction->refresh()->current_price);
        $this->assertDatabaseCount('bids', 1);
    }

    public function test_seller_cannot_self_bid_or_manage_another_sellers_auction(): void
    {
        $auction = Auction::factory()->create();
        $this->login($auction->seller);
        $this->postJson('/api/v1/auctions/'.$auction->id.'/bids', ['amount' => '100.00'])->assertForbidden();
        $this->login(User::factory()->create());
        $this->postJson('/api/v1/seller/auctions', $this->payload($auction->product))->assertForbidden();
        $this->getJson('/api/v1/seller/auctions/'.$auction->id)->assertForbidden();
        $this->postJson('/api/v1/seller/auctions/'.$auction->id.'/cancel')->assertForbidden();
        $this->getJson('/api/v1/seller/auctions')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_seller_and_bid_writes_require_authentication_and_active_account(): void
    {
        $auction = Auction::factory()->create();
        $this->postJson('/api/v1/auctions/'.$auction->id.'/bids', ['amount' => '100.00'])->assertUnauthorized();
        $this->getJson('/api/v1/seller/auctions')->assertUnauthorized();
        $this->getJson('/api/v1/seller/auctions/'.$auction->id)->assertUnauthorized();
        $this->postJson('/api/v1/seller/auctions', $this->payload($auction->product))->assertUnauthorized();
        $this->postJson('/api/v1/seller/auctions/'.$auction->id.'/cancel')->assertUnauthorized();
        $this->login(User::factory()->create(['status' => UserStatus::SUSPENDED]));
        $this->postJson('/api/v1/auctions/'.$auction->id.'/bids', ['amount' => '100.00'])->assertForbidden();
    }

    public function test_bids_before_start_at_end_and_after_end_are_rejected_by_server_time(): void
    {
        $this->freezeTime();
        $auction = Auction::factory()->create(['status' => AuctionStatus::SCHEDULED, 'starts_at' => now()->addMinutes(5), 'ends_at' => now()->addMinutes(10)]);
        $this->login(User::factory()->create());
        $this->postJson('/api/v1/auctions/'.$auction->id.'/bids', ['amount' => '100.00'])->assertConflict();
        $this->travel(5)->minutes();
        app(AuctionService::class)->start($auction);
        $this->postJson('/api/v1/auctions/'.$auction->id.'/bids', ['amount' => '100.00'])->assertCreated();
        $this->travel(5)->minutes();
        $this->postJson('/api/v1/auctions/'.$auction->id.'/bids', ['amount' => '110.00'])->assertConflict();
        $this->travel(1)->seconds();
        $this->postJson('/api/v1/auctions/'.$auction->id.'/bids', ['amount' => '110.00'])->assertConflict();
        $this->assertDatabaseCount('bids', 1);
    }

    public static function unavailableContexts(): array
    {
        return array_map(fn ($value) => [$value], ['hidden', 'deleted_product', 'empty_stock', 'inactive_shop', 'deleted_shop', 'inactive_seller', 'deleted_seller', 'inactive_category', 'inactive_parent']);
    }

    #[DataProvider('unavailableContexts')]
    public function test_unavailable_product_context_is_not_exposed_and_cannot_accept_bids_or_new_auctions(string $state): void
    {
        $auction = Auction::factory()->create();
        $product = $auction->product;
        match ($state) {
            'hidden' => $product->update(['status' => ProductStatus::HIDDEN]), 'deleted_product' => $product->delete(), 'empty_stock' => $product->update(['stock_quantity' => 0]),
            'inactive_shop' => $product->shop->update(['status' => ShopStatus::SUSPENDED]), 'deleted_shop' => $product->shop->delete(),
            'inactive_seller' => $auction->seller->forceFill(['status' => UserStatus::SUSPENDED])->save(), 'deleted_seller' => $auction->seller->delete(),
            'inactive_category' => $product->category->update(['status' => CategoryStatus::INACTIVE]),
            'inactive_parent' => $product->category->update(['parent_id' => Category::factory()->create(['status' => CategoryStatus::INACTIVE])->id]),
        };
        $this->getJson('/api/v1/auctions')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/auctions/'.$auction->id)->assertNotFound();
        $this->getJson('/api/v1/auctions/'.$auction->id.'/bids')->assertNotFound();
        $this->login(User::factory()->create());
        $this->postJson('/api/v1/auctions/'.$auction->id.'/bids', ['amount' => '100.00'])->assertConflict();
        if (! in_array($state, ['inactive_seller', 'deleted_seller', 'deleted_shop'], true)) {
            $this->login($auction->seller);
            $this->postJson('/api/v1/seller/auctions', $this->payload($product))->assertConflict();
        }
    }

    public function test_client_cannot_set_status_current_price_seller_or_winner_or_bid_user(): void
    {
        $product = Product::factory()->active()->create();
        $this->login($product->shop->owner);
        foreach (['status' => 'ENDED', 'current_price' => '999.00', 'winner_user_id' => 1, 'winning_bid_id' => 1, 'seller_id' => 1, 'active_product_id' => null] as $field => $value) {
            $this->postJson('/api/v1/seller/auctions', [...$this->payload($product), $field => $value])->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $auction = Auction::factory()->create();
        $this->login(User::factory()->create());
        $this->postJson('/api/v1/auctions/'.$auction->id.'/bids', ['amount' => '100.00', 'user_id' => $auction->seller_id])->assertUnprocessable();
        $this->postJson('/api/v1/auctions/'.$auction->id.'/bids', ['amount' => '100.00', 'server_time' => now()->subDay()->toISOString()])->assertUnprocessable();
        $this->assertDatabaseCount('bids', 0);
    }

    public static function invalidAmounts(): array
    {
        return array_map(fn ($v) => [$v], ['0', '-1', '1.001', '1e3', '10000000000000.00', 'NaN', 100.0, null]);
    }

    #[DataProvider('invalidAmounts')]
    public function test_invalid_money_is_rejected(mixed $amount): void
    {
        $auction = Auction::factory()->create();
        $this->login(User::factory()->create());
        $this->postJson('/api/v1/auctions/'.$auction->id.'/bids', ['amount' => $amount])->assertUnprocessable();
        $this->assertDatabaseCount('bids', 0);
    }

    public function test_decimal_money_is_exact_and_supported_limit_does_not_overflow(): void
    {
        $auction = Auction::factory()->create(['start_price' => '0.10', 'current_price' => '0.10', 'bid_increment' => '0.20']);
        $this->login(User::factory()->create());
        $this->postJson('/api/v1/auctions/'.$auction->id.'/bids', ['amount' => '0.10'])->assertCreated();
        $this->postJson('/api/v1/auctions/'.$auction->id.'/bids', ['amount' => '0.29'])->assertUnprocessable();
        $this->postJson('/api/v1/auctions/'.$auction->id.'/bids', ['amount' => '0.30'])->assertCreated();
        $this->assertSame('0.30', $auction->refresh()->current_price);
        $auction = Auction::factory()->create(['start_price' => '9999999999999.98', 'current_price' => '9999999999999.98', 'bid_increment' => '0.01']);
        $this->postJson('/api/v1/auctions/'.$auction->id.'/bids', ['amount' => '9999999999999.98'])->assertCreated();
        $this->postJson('/api/v1/auctions/'.$auction->id.'/bids', ['amount' => '9999999999999.99'])->assertCreated();
        $this->postJson('/api/v1/auctions/'.$auction->id.'/bids', ['amount' => '9999999999999.99'])->assertUnprocessable();
        $this->assertSame('9999999999999.99', $auction->refresh()->current_price);
    }

    public function test_finalization_selects_highest_bid_is_idempotent_and_leaves_products_orders_payments_unchanged(): void
    {
        $this->freezeTime();
        $auction = Auction::factory()->create();
        $service = app(AuctionService::class);
        $buyer = User::factory()->create();
        $service->placeBid($buyer, $auction, '100.00');
        $winner = $service->placeBid($buyer, $auction, '120.00');
        $service->placeBid(User::factory()->create(), $auction, '130.00');
        $winner = $auction->bids()->orderByDesc('amount')->first();
        $this->travelTo($auction->ends_at);
        $ended = $service->finalize($auction);
        $updated = $ended->updated_at->toISOString();
        $this->assertSame(AuctionStatus::ENDED, $ended->status);
        $this->assertSame($winner->id, $ended->winning_bid_id);
        $this->assertSame($winner->user_id, $ended->winner_user_id);
        $this->travel(1)->seconds();
        $this->assertSame($updated, $service->finalize($auction)->updated_at->toISOString());
        $this->assertSame(ProductStatus::ACTIVE, $auction->product->refresh()->status);
        $this->assertSame(5, $auction->product->stock_quantity);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_finalization_without_bids_has_no_winner_and_early_finalization_is_rejected(): void
    {
        $auction = Auction::factory()->create();
        $service = app(AuctionService::class);
        try {
            $service->finalize($auction);
            $this->fail('Early finalization accepted');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        $this->travelTo($auction->ends_at);
        $ended = $service->finalize($auction);
        $this->assertSame(AuctionStatus::ENDED, $ended->status);
        $this->assertNull($ended->winner_user_id);
        $this->assertNull($ended->winning_bid_id);
        $this->assertSame($ended->id, $service->finalize($auction)->id);
    }

    public function test_cancellation_is_idempotent_but_disallowed_after_any_bid_or_expiry(): void
    {
        $auction = Auction::factory()->create();
        $this->login($auction->seller);
        $this->postJson('/api/v1/seller/auctions/'.$auction->id.'/cancel')->assertOk()->assertJsonPath('data.status', 'CANCELLED');
        $this->postJson('/api/v1/seller/auctions/'.$auction->id.'/cancel')->assertOk();
        $this->assertSame(AuctionStatus::CANCELLED, app(AuctionService::class)->finalize($auction)->status);
        $auction = Auction::factory()->create();
        app(AuctionService::class)->placeBid(User::factory()->create(), $auction, '100.00');
        $this->login($auction->seller);
        $this->postJson('/api/v1/seller/auctions/'.$auction->id.'/cancel')->assertConflict();
        $this->travelTo($auction->ends_at);
        $this->postJson('/api/v1/seller/auctions/'.$auction->id.'/cancel')->assertConflict();
    }

    public function test_public_resources_are_paginated_include_server_time_and_do_not_expose_account_secrets(): void
    {
        $auction = Auction::factory()->create();
        $buyer = User::factory()->create();
        $service = app(AuctionService::class);
        foreach (['100.00', '110.00', '120.00'] as $amount) {
            $service->placeBid($buyer, $auction, $amount);
        }
        $this->getJson('/api/v1/auctions')->assertOk()->assertJsonStructure(['data', 'meta', 'server_time']);
        $this->getJson('/api/v1/auctions/'.$auction->id)->assertOk()->assertJsonStructure(['data' => ['server_time', 'product']])->assertJsonMissingPath('data.seller.email');
        $this->getJson('/api/v1/auctions/'.$auction->id.'/bids?per_page=2')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 3)->assertJsonMissingPath('data.0.user.email');
        $this->getJson('/api/v1/auctions/'.$auction->id.'/bids?per_page=2&page=2')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_sync_command_starts_due_finalizes_expired_and_is_repeatable(): void
    {
        $this->freezeTime();
        $scheduled = Auction::factory()->create(['status' => AuctionStatus::SCHEDULED, 'starts_at' => now()->addMinute(), 'ends_at' => now()->addHour()]);
        $expired = Auction::factory()->create(['ends_at' => now()->addMinute()]);
        $this->assertSame(AuctionStatus::SCHEDULED, app(AuctionService::class)->start($scheduled)->status);
        $this->travel(1)->minutes();
        $this->artisan('auctions:sync')->assertSuccessful();
        $this->assertSame(AuctionStatus::LIVE, $scheduled->refresh()->status);
        $this->assertSame(AuctionStatus::ENDED, $expired->refresh()->status);
        $this->artisan('auctions:sync')->assertSuccessful();
        $this->artisan('auctions:sync', ['--limit' => 0])->assertExitCode(2);
    }

    public function test_auction_bid_rate_limit_is_applied(): void
    {
        $auction = Auction::factory()->create();
        $this->login(User::factory()->create());
        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/api/v1/auctions/'.$auction->id.'/bids', ['amount' => '1.00'])->assertUnprocessable();
        }
        $this->postJson('/api/v1/auctions/'.$auction->id.'/bids', ['amount' => '100.00'])->assertTooManyRequests();
    }

    public function test_database_prevents_multiple_unfinished_auctions_for_the_same_product(): void
    {
        $auction = Auction::factory()->create();
        $this->expectException(QueryException::class);
        Auction::factory()->create(['product_id' => $auction->product_id]);
    }
}
