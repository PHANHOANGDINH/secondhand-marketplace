<?php

namespace Tests\Feature;

use App\Enums\AuctionStatus;
use App\Events\NotificationCreated;
use App\Models\Address;
use App\Models\Auction;
use App\Models\User;
use App\Services\AuctionCheckoutService;
use App\Services\AuctionNotificationService;
use App\Services\AuctionService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuctionNotificationTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        $this->withHeaders(['Origin' => 'http://localhost:5173', 'Accept' => 'application/json']);
    }

    private function ended(): array
    {
        $auction = Auction::factory()->create();
        $a = User::factory()->create();
        $b = User::factory()->create();
        app(AuctionService::class)->placeBid($a, $auction, '100.00');
        app(AuctionService::class)->placeBid($b, $auction, '110.00');
        $auction->forceFill(['ends_at' => now()->subSecond()])->save();
        $auction = app(AuctionService::class)->finalize($auction);

        return [$auction, $a, $b];
    }

    public function test_guest_cannot_list_count_read_or_read_all_notifications(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
        $this->getJson('/api/v1/notifications/unread-count')->assertUnauthorized();
        $this->postJson('/api/v1/notifications/read-all')->assertUnauthorized();
        $this->postJson('/api/v1/notifications/'.Str::uuid().'/read')->assertUnauthorized();
    }

    public function test_outbid_persists_for_only_the_previous_highest_bidder_and_deduplicates(): void
    {
        $auction = Auction::factory()->create();
        $a = User::factory()->create();
        $b = User::factory()->create();
        $previous = app(AuctionService::class)->placeBid($a, $auction, '100.00');
        $current = app(AuctionService::class)->placeBid($b, $auction, '110.00');
        app(AuctionNotificationService::class)->outbid($auction, $current, $previous);
        $this->assertSame(1, $a->notifications()->where('type', 'OUTBID')->count());
        $this->assertSame(0, $b->notifications()->count());
        $this->assertSame(0, $auction->seller->notifications()->count());
        Event::assertDispatchedTimes(NotificationCreated::class, 1);
    }

    public function test_raising_own_highest_bid_does_not_create_an_outbid_notice(): void
    {
        $auction = Auction::factory()->create();
        $a = User::factory()->create();
        app(AuctionService::class)->placeBid($a, $auction, '100.00');
        app(AuctionService::class)->placeBid($a, $auction, '110.00');
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_finalize_retries_notify_winner_seller_and_distinct_losing_participants_once(): void
    {
        [$auction, $loser, $winner] = $this->ended();
        app(AuctionService::class)->finalize($auction);
        $won = $winner->notifications()->sole();
        $this->assertSame('AUCTION_WON', $won->data['type']);
        $this->assertSame('110.00', $won->data['winning_amount']);
        $this->assertSame($auction->winner_checkout_expires_at->toISOString(), $won->data['checkout_deadline']);
        $this->assertSame('/auctions/'.$auction->id.'/checkout', $won->data['target_route']);
        $this->assertSame(1, $loser->notifications()->where('type', 'AUCTION_ENDED')->count());
        $this->assertSame(1, $auction->seller->notifications()->where('type', 'AUCTION_ENDED')->count());
        $this->assertSame(0, User::factory()->create()->notifications()->count());
        $this->assertDatabaseCount('notifications', 4);
    }

    public function test_no_bid_finalization_and_bid_free_cancellation_notify_only_seller_once(): void
    {
        $auction = Auction::factory()->create(['ends_at' => now()->subSecond()]);
        app(AuctionService::class)->finalize($auction);
        app(AuctionService::class)->finalize($auction);
        $this->assertSame(1, $auction->seller->notifications()->where('type', 'AUCTION_ENDED')->count());
        $other = Auction::factory()->create(['status' => AuctionStatus::SCHEDULED, 'starts_at' => now()->addHour(), 'ends_at' => now()->addHours(2)]);
        app(AuctionService::class)->cancel($other->seller, $other);
        app(AuctionService::class)->cancel($other->seller, $other);
        $this->assertSame(1, $other->seller->notifications()->where('type', 'AUCTION_CANCELLED')->count());
        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_offline_notification_appears_after_a_later_login_and_payload_is_safe(): void
    {
        [$auction, , $winner] = $this->ended();
        $this->actingAs($winner, 'web');
        $response = $this->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('unread_count', 1)->assertJsonPath('data.0.type', 'AUCTION_WON');
        foreach (['password', 'email', 'notifiable_id', 'notifiable_type', 'dedup_key', 'gateway_transaction_id', 'card_number'] as $field) {
            $response->assertJsonMissingPath('data.0.'.$field);
        }
        $this->getJson('/api/v1/notifications/unread-count')->assertOk()->assertJsonPath('data.unread_count', 1);
    }

    public function test_other_user_cannot_read_notification_or_mark_it_read_and_read_all_is_owner_scoped(): void
    {
        [$auction, $loser, $winner] = $this->ended();
        $own = $winner->notifications()->sole();
        $foreign = $loser->notifications()->first();
        $this->actingAs($winner, 'web');
        $this->postJson('/api/v1/notifications/'.$foreign->id.'/read')->assertNotFound();
        $this->postJson('/api/v1/notifications/'.Str::uuid().'/read')->assertNotFound();
        $this->postJson('/api/v1/notifications/'.$own->id.'/read')->assertOk();
        $readAt = $own->refresh()->read_at->toISOString();
        $this->postJson('/api/v1/notifications/'.$own->id.'/read')->assertOk();
        $this->assertSame($readAt, $own->refresh()->read_at->toISOString());
        $this->postJson('/api/v1/notifications/read-all')->assertOk()->assertJsonPath('data.unread_count', 0);
        $this->assertNull($foreign->refresh()->read_at);
        $this->getJson('/api/v1/notifications?filter=unread')->assertJsonCount(0, 'data');
    }

    public function test_pagination_newest_first_filters_and_validation(): void
    {
        [$auction, $loser, $winner] = $this->ended();
        $address = Address::factory()->for($winner, 'user')->create();
        $this->travel(2)->seconds();
        app(AuctionCheckoutService::class)->checkout($winner, $auction, $address->id, (string) Str::uuid());
        $this->actingAs($winner, 'web');
        $this->getJson('/api/v1/notifications?per_page=1')->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.type', 'AUCTION_ORDER_CREATED');
        $this->getJson('/api/v1/notifications?filter=orders')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/notifications?filter=auction')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/notifications?per_page=101')->assertUnprocessable();
        $this->getJson('/api/v1/notifications?filter=other')->assertUnprocessable();
        $this->postJson('/api/v1/notifications/read-all', ['user_id' => $loser->id])->assertUnprocessable();
    }

    public function test_transaction_rollback_does_not_persist_or_broadcast_a_notification(): void
    {
        $auction = Auction::factory()->create();
        $user = User::factory()->create();
        try {
            DB::transaction(function () use ($auction, $user): void {
                app(AuctionNotificationService::class)->persist($user->id, $auction, 'OUTBID', 'rollback', 'Plain safe text', '/auctions/'.$auction->id);
                Event::assertNotDispatched(NotificationCreated::class);
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException $e) {
            $this->assertSame('rollback', $e->getMessage());
        }
        $this->assertDatabaseCount('notifications', 0);
        Event::assertNotDispatched(NotificationCreated::class);
    }

    public function test_one_configurable_reminder_only_within_threshold_and_before_deadline(): void
    {
        [$auction, , $winner] = $this->ended();
        config(['auctions.checkout_reminder_minutes' => 30]);
        $service = app(AuctionNotificationService::class);
        $this->assertFalse($service->remind($auction));
        $auction->forceFill(['winner_checkout_expires_at' => now()->addMinutes(29)])->save();
        $this->assertTrue($service->remind($auction));
        $this->assertFalse($service->remind($auction));
        $this->assertSame(1, $winner->notifications()->where('type', 'WINNER_CHECKOUT_EXPIRING')->count());
        $this->assertNotNull($auction->refresh()->winner_checkout_reminded_at);
        $this->assertSame(5, $auction->product->stock_quantity);
    }

    public function test_expired_or_disabled_reminders_do_not_change_stock_or_create_order(): void
    {
        [$auction, , $winner] = $this->ended();
        $auction->forceFill(['winner_checkout_expires_at' => now()])->save();
        $this->assertFalse(app(AuctionNotificationService::class)->remind($auction));
        $auction->forceFill(['winner_checkout_expires_at' => now()->addMinutes(10)])->save();
        config(['auctions.checkout_reminder_minutes' => 0]);
        $this->assertFalse(app(AuctionNotificationService::class)->remind($auction));
        $this->assertSame(0, $winner->notifications()->where('type', 'WINNER_CHECKOUT_EXPIRING')->count());
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(5, $auction->product->stock_quantity);
    }

    public function test_order_creation_notifies_winner_and_seller_once_and_prevents_reminder(): void
    {
        [$auction, , $winner] = $this->ended();
        $auction->forceFill(['winner_checkout_expires_at' => now()->addMinutes(20)])->save();
        $address = Address::factory()->for($winner, 'user')->create();
        $key = (string) Str::uuid();
        $order = app(AuctionCheckoutService::class)->checkout($winner, $auction, $address->id, $key)['order'];
        app(AuctionCheckoutService::class)->checkout($winner, $auction, $address->id, $key);
        $this->assertFalse(app(AuctionNotificationService::class)->remind($auction));
        $this->assertSame(1, $winner->notifications()->where('type', 'AUCTION_ORDER_CREATED')->count());
        $this->assertSame(1, $auction->seller->notifications()->where('type', 'AUCTION_ORDER_CREATED')->count());
        $this->assertSame('/orders/'.$order->id, $winner->notifications()->where('type', 'AUCTION_ORDER_CREATED')->sole()->data['target_route']);
        $this->assertSame(0, $winner->notifications()->where('type', 'WINNER_CHECKOUT_EXPIRING')->count());
    }

    public function test_repeated_scheduler_runs_finalize_and_remind_once_and_preserve_deadline(): void
    {
        $auction = Auction::factory()->create();
        $winner = User::factory()->create();
        app(AuctionService::class)->placeBid($winner, $auction, '100.00');
        $auction->forceFill(['ends_at' => now()->subSecond()])->save();
        config(['auctions.checkout_reminder_minutes' => 1440]);
        $this->artisan('auctions:sync')->assertSuccessful();
        $deadline = $auction->refresh()->winner_checkout_expires_at->toISOString();
        $this->artisan('auctions:sync')->assertSuccessful();
        $this->assertSame($deadline, $auction->refresh()->winner_checkout_expires_at->toISOString());
        $this->assertSame(1, $winner->notifications()->where('type', 'AUCTION_WON')->count());
        $this->assertSame(1, $winner->notifications()->where('type', 'WINNER_CHECKOUT_EXPIRING')->count());
        $this->assertDatabaseCount('notifications', 3);
    }

    public function test_scheduler_starts_scheduled_auction_once_without_changing_bid_rules(): void
    {
        $auction = Auction::factory()->create(['status' => AuctionStatus::SCHEDULED, 'starts_at' => now()->subMinute()]);
        $this->artisan('auctions:sync')->assertSuccessful();
        $this->artisan('auctions:sync')->assertSuccessful();
        $this->assertSame(AuctionStatus::LIVE, $auction->refresh()->status);
        $this->assertDatabaseCount('notifications', 0);
    }
}
