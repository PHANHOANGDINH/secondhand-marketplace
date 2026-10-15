<?php

namespace Tests\Feature;

use App\Enums\AuctionStatus;
use App\Enums\ProductStatus;
use App\Events\AuctionStatusChanged;
use App\Models\Auction;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuctionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/** "Bán ngay": the seller ends a live auction by accepting its highest bid. */
class AuctionSellNowTest extends TestCase
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

    private function url(Auction $auction): string
    {
        return '/api/v1/seller/auctions/'.$auction->id.'/accept-bid';
    }

    /** A live auction with two bidders; returns [auction, loser, winner, highest bid]. */
    private function liveWithBids(): array
    {
        $auction = Auction::factory()->create();
        $service = app(AuctionService::class);
        $loser = User::factory()->create();
        $winner = User::factory()->create();
        $service->placeBid($loser, $auction, '100.00');
        $top = $service->placeBid($winner, $auction, '150.00');

        return [$auction, $loser, $winner, $top];
    }

    public function test_seller_accepts_the_highest_bid_and_the_auction_ends_now_with_that_winner(): void
    {
        $this->freezeTime();
        [$auction, $loser, $winner, $top] = $this->liveWithBids();
        $originalEnd = $auction->ends_at->toISOString();
        $stock = $auction->product->stock_quantity;
        Event::fake([AuctionStatusChanged::class]);
        $this->login($auction->seller);

        $this->postJson($this->url($auction), ['expected_status' => 'LIVE', 'expected_bid_id' => $top->id, 'reason' => '  Giá đã ổn  '])
            ->assertOk()->assertJsonPath('data.status', 'ENDED')->assertJsonPath('data.winner_user_id', (string) $winner->id)
            ->assertJsonPath('data.winning_bid_id', (string) $top->id)->assertJsonPath('data.current_price', '150.00')->assertJsonPath('data.minimum_next_bid', null);

        $auction->refresh();
        $this->assertSame(AuctionStatus::ENDED, $auction->status);
        $this->assertSame(now()->toISOString(), $auction->ends_at->toISOString());
        $this->assertNotSame($originalEnd, $auction->ends_at->toISOString());
        $this->assertSame(now()->addHours((int) config('auctions.winner_checkout_hours', 24))->toISOString(), $auction->winner_checkout_expires_at->toISOString());
        Event::assertDispatched(AuctionStatusChanged::class, fn (AuctionStatusChanged $e): bool => $e->broadcastWith()['status'] === 'ENDED');

        $audit = AuditLog::where('action', 'auction.accepted_by_seller')->sole();
        $this->assertSame(['auctions', $auction->id, 'LIVE', 'ENDED', $auction->seller_id, 'Giá đã ổn'], [$audit->entity_type, $audit->entity_id, $audit->from_status, $audit->to_status, $audit->actor_id, $audit->reason]);
        $this->assertSame('AUCTION_WON', $winner->notifications()->sole()->data['type']);
        $this->assertSame('/auctions/'.$auction->id.'/checkout', $winner->notifications()->sole()->data['target_route']);
        $this->assertContains('AUCTION_ENDED', $loser->notifications()->pluck('data')->pluck('type')->all());
        $this->assertSame(1, $auction->seller->notifications()->where('data->type', 'AUCTION_ENDED')->count());

        // Nothing is sold yet: the winner checks out as after a normal end.
        $this->assertSame(ProductStatus::ACTIVE, $auction->product->refresh()->status);
        $this->assertSame($stock, $auction->product->stock_quantity);
        $this->assertDatabaseCount('orders', 0);
        $this->login($winner);
        $this->getJson('/api/v1/auctions/'.$auction->id.'/checkout')->assertOk();
        $this->postJson('/api/v1/auctions/'.$auction->id.'/bids', ['amount' => '200.00'])->assertConflict();

        // The scheduled finalizer later leaves it alone.
        $this->travelTo($originalEnd);
        $this->travel(1)->seconds();
        $this->assertSame($top->id, app(AuctionService::class)->finalize($auction)->winning_bid_id);
        $this->assertSame(1, $winner->notifications()->count());
    }

    public function test_a_default_reason_is_recorded_when_the_seller_gives_none(): void
    {
        [$auction, , , $top] = $this->liveWithBids();
        $this->login($auction->seller);
        $this->postJson($this->url($auction), ['expected_status' => 'LIVE', 'expected_bid_id' => $top->id])->assertOk();
        $this->assertSame('Người bán chốt bán ở giá 150.00 ₫.', AuditLog::where('action', 'auction.accepted_by_seller')->sole()->reason);
    }

    public function test_a_newer_bid_a_changed_status_no_bids_or_expiry_is_a_conflict_and_changes_nothing(): void
    {
        [$auction, $loser, , $top] = $this->liveWithBids();
        $newer = app(AuctionService::class)->placeBid($loser, $auction, '160.00');
        $this->login($auction->seller);
        $this->postJson($this->url($auction), ['expected_status' => 'LIVE', 'expected_bid_id' => $top->id])->assertConflict();
        $this->postJson($this->url($auction), ['expected_status' => 'SCHEDULED', 'expected_bid_id' => $newer->id])->assertConflict();
        $this->assertSame(AuctionStatus::LIVE, $auction->refresh()->status);
        $this->assertNull($auction->winner_user_id);

        $empty = Auction::factory()->create();
        $this->login($empty->seller);
        $this->postJson($this->url($empty), ['expected_status' => 'LIVE', 'expected_bid_id' => $newer->id])->assertConflict();
        $this->assertSame(AuctionStatus::LIVE, $empty->refresh()->status);

        $this->login($auction->seller);
        $this->travelTo($auction->ends_at);
        $this->postJson($this->url($auction), ['expected_status' => 'LIVE', 'expected_bid_id' => $newer->id])->assertConflict();
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_cancelled_or_already_ended_auctions_cannot_be_sold_again(): void
    {
        [$auction, , , $top] = $this->liveWithBids();
        $this->login($auction->seller);
        $this->postJson($this->url($auction), ['expected_status' => 'LIVE', 'expected_bid_id' => $top->id])->assertOk();
        $this->postJson($this->url($auction), ['expected_status' => 'LIVE', 'expected_bid_id' => $top->id])->assertConflict();
        $this->postJson($this->url($auction), ['expected_status' => 'ENDED', 'expected_bid_id' => $top->id])->assertConflict();
        $this->assertSame(1, AuditLog::where('action', 'auction.accepted_by_seller')->count());
    }

    public function test_only_the_auction_seller_may_sell_and_input_is_validated(): void
    {
        [$auction, $loser, , $top] = $this->liveWithBids();
        $body = ['expected_status' => 'LIVE', 'expected_bid_id' => $top->id];
        $this->postJson($this->url($auction), $body)->assertUnauthorized();
        $this->login($loser);
        $this->postJson($this->url($auction), $body)->assertForbidden();
        $this->login(Auction::factory()->create()->seller);
        $this->postJson($this->url($auction), $body)->assertForbidden();
        $this->login(User::factory()->admin()->create());
        $this->postJson($this->url($auction), $body)->assertForbidden();
        $this->login($auction->seller);
        $this->postJson($this->url($auction), [])->assertUnprocessable()->assertJsonValidationErrors(['expected_status', 'expected_bid_id']);
        $this->postJson($this->url($auction), [...$body, 'reason' => str_repeat('a', 501)])->assertUnprocessable()->assertJsonValidationErrors(['reason']);
        $this->assertSame(AuctionStatus::LIVE, $auction->refresh()->status);
    }
}
