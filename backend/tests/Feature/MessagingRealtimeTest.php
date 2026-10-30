<?php

namespace Tests\Feature;

use App\Events\ConversationRead;
use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\MessagingService;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class MessagingRealtimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeaders(['Origin' => 'http://localhost:5173', 'Accept' => 'application/json']);
        config(['broadcasting.default' => 'null']);
        Event::fake([MessageSent::class, ConversationRead::class]);
    }

    private function login(User $user): void
    {
        $this->app['session']->flush();
        Auth::forgetGuards();
        $this->actingAs($user, 'web');
    }

    public function test_sent_message_is_broadcast_after_commit_to_both_participants_only(): void
    {
        $conversation = Conversation::factory()->create();
        $buyer = User::findOrFail($conversation->buyer_id);
        $this->login($buyer);
        $id = $this->postJson('/api/v1/conversations/'.$conversation->id.'/messages', ['body' => '  Còn hàng không?  '])->assertCreated()->json('data.id');

        Event::assertDispatchedTimes(MessageSent::class, 1);
        Event::assertDispatched(MessageSent::class, function (MessageSent $event) use ($conversation, $buyer, $id): bool {
            $channels = array_map(fn ($channel) => $channel->name, $event->broadcastOn());
            sort($channels);
            $expected = ['private-user.'.$conversation->buyer_id, 'private-user.'.$conversation->seller_id];
            sort($expected);
            $this->assertSame($expected, $channels);
            $this->assertSame('MessageSent', $event->broadcastAs());
            $payload = $event->broadcastWith();
            $this->assertSame(['conversation_id', 'message'], array_keys($payload));
            $this->assertSame((string) $conversation->id, $payload['conversation_id']);
            $this->assertSame(['id', 'sender_id', 'body', 'created_at'], array_keys($payload['message']));
            $this->assertSame($id, $payload['message']['id']);
            $this->assertSame((string) $buyer->id, $payload['message']['sender_id']);
            $this->assertSame('Còn hàng không?', $payload['message']['body']);
            $this->assertStringNotContainsString($buyer->email, json_encode($payload));

            return true;
        });
        Event::assertNotDispatched(ConversationRead::class);
    }

    public function test_events_are_rescued_after_commit_broadcasts(): void
    {
        foreach ([MessageSent::class, ConversationRead::class] as $class) {
            $this->assertTrue(is_subclass_of($class, ShouldBroadcastNow::class));
            $this->assertTrue(is_subclass_of($class, ShouldDispatchAfterCommit::class));
            $this->assertTrue(is_subclass_of($class, ShouldRescue::class));
        }
    }

    public function test_rejected_send_and_outer_rollback_broadcast_nothing(): void
    {
        $conversation = Conversation::factory()->create();
        $this->login(User::factory()->create());
        $this->postJson('/api/v1/conversations/'.$conversation->id.'/messages', ['body' => 'Hello'])->assertForbidden();
        $this->login(User::findOrFail($conversation->buyer_id));
        $this->postJson('/api/v1/conversations/'.$conversation->id.'/messages', ['body' => '   '])->assertUnprocessable();

        DB::beginTransaction();
        app(MessagingService::class)->send(User::findOrFail($conversation->buyer_id), $conversation, 'Rolled back');
        DB::rollBack();

        $this->assertDatabaseCount('messages', 0);
        Event::assertNotDispatched(MessageSent::class);
    }

    public function test_read_checkpoint_broadcasts_only_to_reader_and_only_when_it_advances(): void
    {
        $conversation = Conversation::factory()->create();
        $buyer = User::findOrFail($conversation->buyer_id);
        $message = Message::factory()->for($conversation)->create(['sender_id' => $conversation->seller_id]);
        $this->login($buyer);
        $read = fn (int $id) => $this->postJson('/api/v1/conversations/'.$conversation->id.'/read', ['last_message_id' => $id])->assertOk();

        $read($message->id);
        Event::assertDispatchedTimes(ConversationRead::class, 1);
        Event::assertDispatched(ConversationRead::class, function (ConversationRead $event) use ($buyer, $conversation, $message): bool {
            $this->assertSame(['private-user.'.$buyer->id], array_map(fn ($channel) => $channel->name, $event->broadcastOn()));
            $this->assertSame(['conversation_id' => (string) $conversation->id, 'last_read_message_id' => (string) $message->id], $event->broadcastWith());

            return true;
        });

        $read($message->id);
        $read(0);
        Event::assertDispatchedTimes(ConversationRead::class, 1);
        $this->postJson('/api/v1/conversations/'.$conversation->id.'/read', ['last_message_id' => $message->id + 999])->assertUnprocessable();
        Event::assertDispatchedTimes(ConversationRead::class, 1);
    }
}
