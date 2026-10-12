<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MessagingTest extends TestCase
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

    private function start(Product $product): string
    {
        return $this->postJson('/api/v1/conversations', ['product_id' => $product->id])->assertSuccessful()->json('data.id');
    }

    public function test_all_messaging_endpoints_require_authentication(): void
    {
        $conversation = Conversation::factory()->create();
        $this->getJson('/api/v1/conversations')->assertUnauthorized();
        $this->postJson('/api/v1/conversations', ['seller_id' => $conversation->seller_id])->assertUnauthorized();
        $this->getJson('/api/v1/conversations/'.$conversation->id.'/messages')->assertUnauthorized();
        $this->postJson('/api/v1/conversations/'.$conversation->id.'/messages', ['body' => 'Hello'])->assertUnauthorized();
        $this->postJson('/api/v1/conversations/'.$conversation->id.'/read', ['last_message_id' => 0])->assertUnauthorized();
    }

    public function test_product_owner_is_derived_server_side_and_duplicate_start_is_reused(): void
    {
        $buyer = User::factory()->create();
        $product = Product::factory()->active()->create();
        $this->login($buyer);
        $first = $this->postJson('/api/v1/conversations', ['product_id' => $product->id])->assertCreated()->assertJsonPath('data.product.name', $product->name)->json('data.id');
        $this->postJson('/api/v1/conversations', ['product_id' => $product->id])->assertOk()->assertJsonPath('data.id', $first);
        $this->postJson('/api/v1/conversations', ['product_id' => $product->id, 'seller_id' => $buyer->id])->assertUnprocessable();
        $this->postJson('/api/v1/conversations', ['product_id' => $product->id, 'buyer_id' => $product->shop->owner_id])->assertUnprocessable();
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('conversation_participants', 2);
        $this->assertDatabaseHas('conversations', ['buyer_id' => $buyer->id, 'seller_id' => $product->shop->owner_id]);
        $this->start(Product::factory()->for($product->shop)->active()->create());
        $this->assertDatabaseCount('conversations', 2);
    }

    public function test_direct_seller_conversation_is_optional_product_context_and_idempotent(): void
    {
        $product = Product::factory()->active()->create();
        $this->login(User::factory()->create());
        $data = ['seller_id' => $product->shop->owner_id];
        $id = $this->postJson('/api/v1/conversations', $data)->assertCreated()->assertJsonPath('data.product', null)->json('data.id');
        $this->postJson('/api/v1/conversations', $data)->assertOk()->assertJsonPath('data.id', $id);
        $this->postJson('/api/v1/conversations', [])->assertUnprocessable();
        $this->postJson('/api/v1/conversations', ['seller_id' => User::factory()->create()->id])->assertNotFound();
    }

    public function test_self_chat_is_rejected_for_both_entry_points(): void
    {
        $product = Product::factory()->active()->create();
        $this->login($product->shop->owner);
        $this->postJson('/api/v1/conversations', ['product_id' => $product->id])->assertUnprocessable();
        $this->postJson('/api/v1/conversations', ['seller_id' => $product->shop->owner_id])->assertUnprocessable();
        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_only_participants_can_read_send_or_mark_read_even_if_stranger_is_admin(): void
    {
        $conversation = Conversation::factory()->create();
        Message::factory()->for($conversation)->create();
        foreach (['USER', 'ADMIN'] as $role) {
            $this->login(User::factory()->create(['role' => $role]));
            $this->getJson('/api/v1/conversations')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('unread_total', 0);
            $this->getJson('/api/v1/conversations/'.$conversation->id.'/messages')->assertForbidden();
            $this->postJson('/api/v1/conversations/'.$conversation->id.'/messages', ['body' => 'Intrusion'])->assertForbidden();
            $this->postJson('/api/v1/conversations/'.$conversation->id.'/read', ['last_message_id' => 0])->assertForbidden();
        }
        foreach ([$conversation->buyer_id, $conversation->seller_id] as $index => $id) {
            $this->login(User::findOrFail($id));
            $this->getJson('/api/v1/conversations/'.$conversation->id.'/messages')->assertOk()->assertJsonCount(1 + $index, 'data');
            $this->postJson('/api/v1/conversations/'.$conversation->id.'/messages', ['body' => 'Reply'])->assertCreated();
        }
    }

    public function test_message_validation_trims_whitespace_limits_text_and_rejects_client_owned_fields(): void
    {
        $conversation = Conversation::factory()->create();
        $this->login(User::findOrFail($conversation->buyer_id));
        $url = '/api/v1/conversations/'.$conversation->id.'/messages';
        foreach ([[], ['body' => '  '], ['body' => "\u{00A0}\u{2003}"], ['body' => str_repeat('á', 1001)], ['body' => ['invalid']], ['body' => 'Hello', 'sender_id' => $conversation->seller_id], ['body' => 'Hello', 'conversation_id' => 999]] as $data) {
            $this->postJson($url, $data)->assertUnprocessable();
        }
        $this->postJson($url, ['body' => " \n Xin chào \n "])->assertCreated()->assertJsonPath('data.body', 'Xin chào')->assertJsonPath('data.sender_id', (string) $conversation->buyer_id);
        $this->postJson($url, ['body' => str_repeat('á', 1000)])->assertCreated();
        $literal = '<script>alert(1)</script> & văn bản';
        $this->postJson($url, ['body' => $literal])->assertCreated()->assertJsonPath('data.body', $literal);
        $this->assertSame($literal, Message::latest('id')->firstOrFail()->body);
        $this->assertNotNull($conversation->fresh()->last_message_at);
    }

    public function test_unread_tracking_is_per_participant_monotonic_and_does_not_consume_newer_unseen_messages(): void
    {
        $conversation = Conversation::factory()->create();
        $this->login(User::findOrFail($conversation->buyer_id));
        $first = $this->postJson('/api/v1/conversations/'.$conversation->id.'/messages', ['body' => 'First'])->assertCreated()->json('data.id');
        $this->getJson('/api/v1/conversations')->assertJsonPath('data.0.unread_count', 0);
        $this->login(User::findOrFail($conversation->seller_id));
        $this->getJson('/api/v1/conversations')->assertJsonPath('data.0.unread_count', 1)->assertJsonPath('unread_total', 1);
        $this->getJson('/api/v1/conversations/'.$conversation->id.'/messages')->assertOk();
        Message::factory()->for($conversation)->create(['sender_id' => $conversation->buyer_id, 'body' => 'Arrived after history fetch']);
        $this->postJson('/api/v1/conversations/'.$conversation->id.'/read', ['last_message_id' => $first])->assertOk()->assertJsonPath('data.unread_count', 1);
        $this->postJson('/api/v1/conversations/'.$conversation->id.'/read', ['last_message_id' => 0])->assertOk()->assertJsonPath('data.last_read_message_id', $first);
        $last = (string) $conversation->messages()->max('id');
        $this->postJson('/api/v1/conversations/'.$conversation->id.'/read', ['last_message_id' => $last])->assertOk()->assertJsonPath('data.unread_count', 0);
        $this->getJson('/api/v1/conversations')->assertJsonPath('unread_total', 0);
        $this->assertDatabaseHas('conversation_participants', ['conversation_id' => $conversation->id, 'user_id' => $conversation->buyer_id, 'last_read_message_id' => 0]);
    }

    public function test_read_checkpoint_cannot_reference_another_conversation_or_a_future_message(): void
    {
        $conversation = Conversation::factory()->create();
        $foreign = Message::factory()->create();
        $this->login(User::findOrFail($conversation->buyer_id));
        $url = '/api/v1/conversations/'.$conversation->id.'/read';
        $this->postJson($url, ['last_message_id' => $foreign->id])->assertUnprocessable();
        $this->postJson($url, ['last_message_id' => $foreign->id + 100])->assertUnprocessable();
        $this->postJson($url, [])->assertUnprocessable();
    }

    public function test_history_pagination_and_cursors_are_stable_when_new_messages_arrive(): void
    {
        $conversation = Conversation::factory()->create();
        $messages = Message::factory()->count(5)->for($conversation)->create();
        $this->login(User::findOrFail($conversation->buyer_id));
        $url = '/api/v1/conversations/'.$conversation->id.'/messages';
        $this->getJson($url.'?per_page=2')->assertOk()->assertJsonPath('meta.total', 5)->assertJsonPath('data.0.id', (string) $messages[4]->id)->assertJsonPath('data.1.id', (string) $messages[3]->id);
        $new = Message::factory()->for($conversation)->create();
        $this->getJson($url.'?per_page=2&before_id='.$messages[3]->id)->assertOk()->assertJsonPath('data.0.id', (string) $messages[2]->id)->assertJsonPath('data.1.id', (string) $messages[1]->id);
        $this->getJson($url.'?after_id='.$messages[4]->id)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', (string) $new->id);
        $this->getJson($url.'?after_id=0&before_id=5')->assertUnprocessable();
        $this->getJson($url.'?per_page=51')->assertUnprocessable();
        $this->getJson('/api/v1/conversations?user_id=999')->assertUnprocessable();
    }

    public function test_unavailable_products_cannot_start_new_conversations_and_context_is_redacted_from_existing_history(): void
    {
        $product = Product::factory()->active()->create(['name' => 'Private product identity']);
        $this->login(User::factory()->create());
        $id = $this->start($product);
        foreach ([ProductStatus::HIDDEN, ProductStatus::SOLD, ProductStatus::DRAFT] as $status) {
            $product->update(['status' => $status]);
            $this->postJson('/api/v1/conversations', ['product_id' => $product->id])->assertNotFound();
            $response = $this->getJson('/api/v1/conversations')->assertOk()->assertJsonPath('data.0.product', null)->assertJsonPath('data.0.product_unavailable', true);
            $this->assertStringNotContainsString('Private product identity', $response->getContent());
        }
        $product->delete();
        $this->postJson('/api/v1/conversations', ['product_id' => $product->id])->assertNotFound();
        $this->postJson('/api/v1/conversations/'.$id.'/messages', ['body' => 'Still readable'])->assertCreated();
        $this->getJson('/api/v1/conversations/'.$id.'/messages')->assertOk()->assertJsonPath('data.0.body', 'Still readable');
        $this->getJson('/api/v1/conversations')->assertJsonPath('data.0.product', null);
    }

    public function test_visibility_of_shop_category_and_owner_is_respected_without_leaking_account_fields(): void
    {
        $this->login(User::factory()->create());
        $product = Product::factory()->active()->create();
        $this->start($product);
        $response = $this->getJson('/api/v1/conversations')->assertOk();
        $response->assertJsonMissingPath('data.0.partner.email')->assertJsonMissingPath('data.0.partner.password')->assertJsonMissingPath('data.0.context_key');
        $product->category->update(['status' => 'INACTIVE']);
        $this->postJson('/api/v1/conversations', ['product_id' => $product->id])->assertNotFound();
        $this->getJson('/api/v1/conversations')->assertJsonPath('data.0.product', null);
        $product->shop->update(['status' => 'SUSPENDED']);
        $this->postJson('/api/v1/conversations', ['seller_id' => $product->shop->owner_id])->assertNotFound();
    }

    public function test_suspended_participant_is_denied_and_messages_are_rate_limited(): void
    {
        $conversation = Conversation::factory()->create();
        $user = User::findOrFail($conversation->buyer_id);
        $this->login($user);
        $user->forceFill(['status' => 'SUSPENDED'])->save();
        $this->getJson('/api/v1/conversations')->assertForbidden();
        $this->postJson('/api/v1/conversations/'.$conversation->id.'/messages', ['body' => 'Blocked'])->assertForbidden();
        $user->forceFill(['status' => 'ACTIVE'])->save();
        $conversation = Conversation::factory()->create();
        $this->login(User::findOrFail($conversation->buyer_id));
        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/api/v1/conversations/'.$conversation->id.'/messages', ['body' => 'Message '.$i])->assertCreated();
        }
        $this->postJson('/api/v1/conversations/'.$conversation->id.'/messages', ['body' => 'Excess'])->assertTooManyRequests();
        $this->assertDatabaseCount('messages', 20);
    }

    public function test_list_is_paginated_unread_total_covers_all_pages_and_queries_are_bounded(): void
    {
        $buyer = User::factory()->create();
        $this->login($buyer);
        $conversations = Conversation::factory()->count(4)->create(['buyer_id' => $buyer->id]);
        foreach ($conversations as $conversation) {
            Message::factory()->for($conversation)->create(['sender_id' => $conversation->seller_id]);
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/v1/conversations?per_page=2')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 4)->assertJsonPath('unread_total', 4);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThan(15, $queries);
        $this->getJson('/api/v1/conversations?page=2&per_page=2')->assertOk()->assertJsonCount(2, 'data');
    }
}
