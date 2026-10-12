<?php

namespace Tests\Feature;

use App\Enums\AuctionStatus;
use App\Enums\ProductStatus;
use App\Enums\UserStatus;
use App\Events\AuctionBidPlaced;
use App\Events\AuctionOutbid;
use App\Events\AuctionStatusChanged;
use App\Models\Auction;
use App\Models\User;
use App\Services\AuctionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AuctionRealtimeTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeaders(['Origin' => 'http://localhost:5173', 'Accept' => 'application/json']);
        config(['broadcasting.default' => 'null']);
    }

    private function fakeEvents(): void
    {
        Event::fake([AuctionBidPlaced::class, AuctionOutbid::class, AuctionStatusChanged::class]);
    }

    public function test_bid_event_waits_for_outer_commit_and_contains_only_safe_snapshot_data(): void
    {
        $auction = Auction::factory()->create();
        $buyer = User::factory()->create();
        $this->fakeEvents();
        DB::beginTransaction();
        $bid = app(AuctionService::class)->placeBid($buyer, $auction, '100.00');
        Event::assertNotDispatched(AuctionBidPlaced::class);
        DB::commit();
        Event::assertDispatched(AuctionBidPlaced::class, function (AuctionBidPlaced $event) use ($auction, $bid, $buyer): bool {
            $payload = $event->broadcastWith();
            $this->assertSame('auction.'.$auction->id, $event->broadcastOn()[0]->name);
            $this->assertSame(['auction_id', 'current_price', 'minimum_next_bid', 'bid_count', 'latest_bid', 'updated_at', 'server_time'], array_keys($payload));
            $this->assertSame(['id', 'bidder_label', 'amount', 'created_at'], array_keys($payload['latest_bid']));
            $this->assertSame('100.00', $payload['current_price']);
            $this->assertSame('110.00', $payload['minimum_next_bid']);
            $this->assertSame(1, $payload['bid_count']);
            $this->assertSame((string) $bid->id, $payload['latest_bid']['id']);
            $this->assertStringNotContainsString($buyer->email, json_encode($payload));
            $this->assertStringNotContainsString($buyer->name, json_encode($payload));
            $this->assertSame(0, DB::transactionLevel());

            return true;
        });
        Event::assertNotDispatched(AuctionOutbid::class);
    }

    public function test_outer_rollback_discards_bid_price_change_and_all_success_events(): void
    {
        $auction = Auction::factory()->create();
        $this->fakeEvents();
        DB::beginTransaction();
        app(AuctionService::class)->placeBid(User::factory()->create(), $auction, '100.00');
        DB::rollBack();
        $this->assertSame(0, $auction->bids()->count());
        $this->assertSame('100.00', $auction->refresh()->current_price);
        Event::assertNotDispatched(AuctionBidPlaced::class);
        Event::assertNotDispatched(AuctionOutbid::class);
    }

    public function test_outbid_is_only_sent_to_a_different_previous_highest_bidder(): void
    {
        $auction = Auction::factory()->create();
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->fakeEvents();
        $service = app(AuctionService::class);
        $service->placeBid($a, $auction, '100.00');
        $service->placeBid($a, $auction, '110.00');
        Event::assertNotDispatched(AuctionOutbid::class);
        $service->placeBid($b, $auction, '120.00');
        Event::assertDispatchedTimes(AuctionBidPlaced::class, 3);
        Event::assertDispatchedTimes(AuctionOutbid::class, 1);
        Event::assertDispatched(AuctionOutbid::class, function (AuctionOutbid $event) use ($a, $auction): bool {
            $this->assertSame('private-user.'.$a->id, $event->broadcastOn()[0]->name);
            $this->assertSame(['auction_id', 'product', 'new_current_price', 'minimum_next_bid', 'server_time'], array_keys($event->broadcastWith()));
            $this->assertSame('120.00', $event->broadcastWith()['new_current_price']);
            $this->assertSame('130.00', $event->broadcastWith()['minimum_next_bid']);
            $this->assertSame((string) $auction->product_id, $event->broadcastWith()['product']['id']);

            return true;
        });
    }

    public function test_rejected_self_insufficient_and_expired_bids_emit_nothing(): void
    {
        $auction = Auction::factory()->create();
        $this->fakeEvents();
        $service = app(AuctionService::class);
        foreach ([[$auction->seller, '100.00'], [User::factory()->create(), '99.99']] as [$user,$amount]) {
            try {
                $service->placeBid($user, $auction, $amount);
                $this->fail('Invalid bid accepted');
            } catch (AuthorizationException|HttpException $e) {
                $this->assertTrue(true);
            }
        }
        $this->travelTo($auction->ends_at);
        try {
            $service->placeBid(User::factory()->create(), $auction, '100.00');
            $this->fail('Expired bid accepted');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        Event::assertNotDispatched(AuctionBidPlaced::class);
        Event::assertNotDispatched(AuctionOutbid::class);
        $this->assertDatabaseCount('bids', 0);
    }

    public function test_lifecycle_events_are_after_commit_and_retries_do_not_rebroadcast(): void
    {
        $service = app(AuctionService::class);
        $this->fakeEvents();
        $scheduled = Auction::factory()->create(['status' => AuctionStatus::SCHEDULED]);
        DB::beginTransaction();
        $service->start($scheduled);
        Event::assertNotDispatched(AuctionStatusChanged::class);
        DB::commit();
        $service->start($scheduled);
        $cancelled = Auction::factory()->create();
        $service->cancel($cancelled->seller, $cancelled);
        $service->cancel($cancelled->seller, $cancelled);
        $expired = Auction::factory()->create(['ends_at' => now()->subSecond()]);
        $service->finalize($expired);
        $service->finalize($expired);
        Event::assertDispatchedTimes(AuctionStatusChanged::class, 3);
        Event::assertDispatched(AuctionStatusChanged::class, function (AuctionStatusChanged $event): bool {
            $this->assertSame(['auction_id', 'status', 'starts_at', 'ends_at', 'current_price', 'updated_at', 'server_time'], array_keys($event->broadcastWith()));

            return true;
        });
    }

    public function test_public_event_is_suppressed_if_product_is_no_longer_public_at_delivery(): void
    {
        $auction = Auction::factory()->create();
        $event = new AuctionStatusChanged(['auction_id' => (string) $auction->id]);
        $this->assertTrue($event->broadcastWhen());
        $auction->product->update(['status' => ProductStatus::HIDDEN]);
        $this->assertFalse($event->broadcastWhen());
    }

    public function test_own_private_channel_is_signed_and_other_users_are_forbidden(): void
    {
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'test-key', 'broadcasting.connections.reverb.secret' => 'test-secret', 'broadcasting.connections.reverb.app_id' => 'test']);
        require base_path('routes/channels.php');
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user, 'web');
        $this->postJson('/api/v1/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => 'private-user.'.$user->id])->assertOk()->assertJsonPath('auth', 'test-key:'.hash_hmac('sha256', '123.456:private-user.'.$user->id, 'test-secret'));
        $this->postJson('/api/v1/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => 'private-user.'.$other->id])->assertForbidden();
    }

    public function test_guests_and_inactive_users_cannot_authorize_private_channels(): void
    {
        $user = User::factory()->create(['status' => UserStatus::SUSPENDED]);
        $this->postJson('/api/v1/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => 'private-user.'.$user->id])->assertUnauthorized();
        $this->actingAs($user, 'web');
        $this->postJson('/api/v1/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => 'private-user.'.$user->id])->assertForbidden();
    }

    public function test_tampered_channel_socket_and_extra_fields_are_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');
        foreach (['private-user.0', 'private-user.01', 'private-user.1.extra', 'private-auction.1', 'auction.1'] as $channel) {
            $this->postJson('/api/v1/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => $channel])->assertUnprocessable();
        }
        $this->postJson('/api/v1/broadcasting/auth', ['socket_id' => 'bad-socket', 'channel_name' => 'private-user.'.$user->id])->assertUnprocessable();
        $this->postJson('/api/v1/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => 'private-user.'.$user->id, 'user_id' => $user->id])->assertUnprocessable();
        $this->getJson('/api/v1/broadcasting/auth')->assertStatus(405);
    }

    public function test_broadcast_failure_does_not_undo_committed_bid_or_break_rest_response(): void
    {
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'test-key', 'broadcasting.connections.reverb.secret' => 'test-secret', 'broadcasting.connections.reverb.app_id' => 'test']);
        config(['broadcasting.connections.reverb.options.port' => 1, 'broadcasting.connections.reverb.client_options.timeout' => 1, 'broadcasting.connections.reverb.client_options.connect_timeout' => 0.2]);
        $auction = Auction::factory()->create();
        $this->actingAs(User::factory()->create(), 'web');
        $this->postJson('/api/v1/auctions/'.$auction->id.'/bids', ['amount' => '100.00'])->assertCreated();
        $this->assertSame(1, $auction->bids()->count());
    }
}
