<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\ReviewStatus;
use App\Enums\ShopOrderStatus;
use App\Enums\ShopStatus;
use App\Enums\UserStatus;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\ShopOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeaders(['Origin' => 'http://localhost:5173', 'Accept' => 'application/json']);
    }

    private function item(?Product $product = null): OrderItem
    {
        $part = ShopOrder::factory()->completed();
        if ($product) {
            $part = $part->state(['shop_id' => $product->shop_id]);
        }

        return OrderItem::factory()->for($part)->create($product ? ['product_id' => $product->id] : []);
    }

    private function login(User $user): void
    {
        $this->app['session']->flush();
        Auth::forgetGuards();
        $this->actingAs($user, 'web');
    }

    private function createReview(OrderItem $item, array $body = ['rating' => 5, 'comment' => 'Good item']): TestResponse
    {
        return $this->postJson('/api/v1/order-items/'.$item->id.'/review', $body);
    }

    public function test_guests_cannot_create_update_or_check_personal_eligibility(): void
    {
        $item = $this->item();
        $review = Review::factory()->for($item)->create();
        $this->createReview($item)->assertUnauthorized();
        $this->patchJson('/api/v1/reviews/'.$review->id, ['rating' => 4])->assertUnauthorized();
        $this->getJson('/api/v1/products/'.$item->product->slug.'/review-eligibility')->assertUnauthorized();
    }

    public function test_only_actual_buyer_can_review_and_user_product_status_injection_is_rejected(): void
    {
        $item = $this->item();
        $this->login(User::factory()->create());
        $this->createReview($item)->assertForbidden();
        $this->login($item->shopOrder->order->buyer);
        foreach (['user_id', 'product_id', 'order_item_id', 'status', 'moderation_reason'] as $field) {
            $this->createReview($item, ['rating' => 5, $field => '1'])->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $result = $this->createReview($item)->assertCreated()->assertJsonPath('data.rating', 5)->assertJsonPath('data.status', 'PUBLISHED')->assertJsonMissingPath('data.user_id')->assertJsonMissingPath('data.moderation_reason');
        $this->assertDatabaseHas('reviews', ['id' => $result->json('data.id'), 'user_id' => $item->shopOrder->order->buyer_id, 'product_id' => $item->product_id, 'order_item_id' => $item->id]);
    }

    public function test_every_non_completed_state_is_ineligible(): void
    {
        $item = $this->item();
        $this->login($item->shopOrder->order->buyer);
        foreach (['PENDING', 'CONFIRMED', 'PROCESSING', 'SHIPPING', 'CANCELLED'] as $state) {
            $item->shopOrder->update(['status' => $state]);
            $this->createReview($item)->assertConflict();
        }
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_review_is_unique_per_order_item_including_hidden_reviews(): void
    {
        $item = $this->item();
        $this->login($item->shopOrder->order->buyer);
        $this->createReview($item)->assertCreated();
        $this->createReview($item)->assertConflict();
        $item->review->update(['status' => ReviewStatus::HIDDEN, 'moderation_reason' => 'Internal reason']);
        $this->createReview($item)->assertConflict();
        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_rating_comment_and_unknown_fields_are_validated(): void
    {
        $item = $this->item();
        $this->login($item->shopOrder->order->buyer);
        foreach ([0, 6, -1, 1.5, 'bad', null] as $rating) {
            $this->createReview($item, ['rating' => $rating])->assertUnprocessable()->assertJsonValidationErrors('rating');
        }
        $this->createReview($item, ['rating' => 5, 'comment' => str_repeat('x', 2001)])->assertUnprocessable();
        $this->createReview($item, ['rating' => 5, 'comment' => []])->assertUnprocessable();
        $id = $this->createReview($item, ['rating' => 1, 'comment' => null])->assertCreated()->assertJsonPath('data.comment', null)->json('data.id');
        $this->patchJson('/api/v1/reviews/'.$id, ['rating' => 6])->assertUnprocessable();
        $this->patchJson('/api/v1/reviews/'.$id, ['rating' => 5, 'comment' => str_repeat('x', 2000)])->assertOk()->assertJsonPath('data.rating', 5);
    }

    public function test_own_shop_review_and_mismatched_product_reference_are_rejected(): void
    {
        $item = $this->item();
        $owner = $item->shopOrder->shop->owner;
        $item->shopOrder->order->update(['buyer_id' => $owner->id]);
        $this->login($owner);
        $this->createReview($item)->assertForbidden();
        $other = $this->item();
        $this->login($other->shopOrder->order->buyer);
        $other->update(['product_id' => $item->product_id]);
        $this->createReview($other)->assertConflict();
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_author_can_edit_but_foreign_user_seller_and_status_changes_are_forbidden(): void
    {
        $item = $this->item();
        $review = Review::factory()->for($item)->create();
        foreach ([User::factory()->create(), $item->shopOrder->shop->owner] as $user) {
            $this->login($user);
            $this->patchJson('/api/v1/reviews/'.$review->id, ['rating' => 1])->assertForbidden();
        }
        $this->login($item->shopOrder->order->buyer);
        $this->patchJson('/api/v1/reviews/'.$review->id, ['rating' => 3, 'comment' => 'Updated'])->assertOk()->assertJsonPath('data.rating', 3)->assertJsonPath('data.comment', 'Updated');
        $review->update(['status' => ReviewStatus::HIDDEN, 'moderation_reason' => 'Internal']);
        $this->patchJson('/api/v1/reviews/'.$review->id, ['comment' => null])->assertOk()->assertJsonPath('data.status', 'HIDDEN')->assertJsonPath('data.comment', null)->assertJsonMissingPath('data.moderation_reason');
        foreach (['status', 'user_id', 'product_id', 'order_item_id'] as $field) {
            $this->patchJson('/api/v1/reviews/'.$review->id, [$field => '1'])->assertUnprocessable();
        }
        $this->deleteJson('/api/v1/reviews/'.$review->id)->assertStatus(405);
        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_suspended_author_cannot_mutate(): void
    {
        $item = $this->item();
        $user = $item->shopOrder->order->buyer;
        $user->forceFill(['status' => UserStatus::SUSPENDED])->save();
        $this->login($user);
        $this->createReview($item)->assertForbidden();
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_public_list_pagination_privacy_and_aggregate_use_only_visible_valid_reviews(): void
    {
        $item = $this->item();
        $product = $item->product;
        $a = Review::factory()->for($item)->create(['rating' => 5]);
        $b = Review::factory()->for($this->item($product))->create(['rating' => 4]);
        Review::factory()->for($this->item($product))->create(['rating' => 1, 'status' => ReviewStatus::HIDDEN]);
        $pending = $this->item($product);
        $pending->shopOrder->update(['status' => ShopOrderStatus::PENDING]);
        Review::factory()->for($pending)->create(['rating' => 1]);
        $this->getJson('/api/v1/products/'.$product->slug)->assertOk()->assertJsonPath('data.review_count', 2)->assertJsonPath('data.average_rating', 4.5);
        $this->getJson('/api/v1/products/'.$product->slug.'/reviews?per_page=1')->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.id', (string) $b->id)->assertJsonMissingPath('data.0.user_id')->assertJsonMissingPath('data.0.author.email')->assertJsonMissingPath('data.0.order_item_id')->assertJsonMissingPath('data.0.moderation_reason');
        $this->getJson('/api/v1/products/'.$product->slug.'/reviews?per_page=1&page=2')->assertOk()->assertJsonPath('data.0.id', (string) $a->id);
        $this->getJson('/api/v1/products/'.$product->slug.'/reviews?per_page=101')->assertUnprocessable();
        $this->getJson('/api/v1/products/'.$product->slug.'/reviews?status=HIDDEN')->assertUnprocessable();
        $this->login($item->shopOrder->order->buyer);
        $this->patchJson('/api/v1/reviews/'.$a->id, ['rating' => 2])->assertOk();
        $this->getJson('/api/v1/products/'.$product->slug)->assertJsonPath('data.review_count', 2)->assertJsonPath('data.average_rating', 3);
    }

    public function test_empty_summary_and_private_product_reviews_do_not_leak(): void
    {
        $item = $this->item();
        $product = $item->product;
        $this->getJson('/api/v1/products/'.$product->slug)->assertOk()->assertJsonPath('data.review_count', 0)->assertJsonPath('data.average_rating', null);
        Review::factory()->for($item)->create();
        $product->update(['status' => ProductStatus::HIDDEN]);
        $this->getJson('/api/v1/products/'.$product->slug.'/reviews')->assertNotFound();
        $product->update(['status' => ProductStatus::ACTIVE]);
        $product->shop->update(['status' => ShopStatus::SUSPENDED]);
        $this->getJson('/api/v1/products/'.$product->slug.'/reviews')->assertNotFound();
    }

    public function test_history_survives_product_soft_delete_and_can_still_be_reviewed_from_order(): void
    {
        $item = $this->item();
        $this->login($item->shopOrder->order->buyer);
        $product = $item->product;
        $product->delete();
        $id = $this->createReview($item)->assertCreated()->json('data.id');
        $this->patchJson('/api/v1/reviews/'.$id, ['comment' => 'History kept'])->assertOk();
        $this->getJson('/api/v1/products/'.$product->slug.'/reviews')->assertNotFound();
        $this->getJson('/api/v1/orders/'.$item->shopOrder->order_id)->assertOk()->assertJsonPath('data.shop_orders.0.items.0.review.id', $id)->assertJsonPath('data.shop_orders.0.items.0.can_review', false)->assertJsonPath('data.shop_orders.0.items.0.can_edit_review', true);
        $this->assertTrue(Review::findOrFail($id)->product->trashed());
        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_order_actions_and_product_eligibility_are_scoped_and_hidden_review_is_editable(): void
    {
        $item = $this->item();
        $buyer = $item->shopOrder->order->buyer;
        $this->login($buyer);
        $path = '/api/v1/products/'.$item->product->slug.'/review-eligibility';
        $this->getJson($path)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.can_review', true)->assertJsonPath('data.0.review', null);
        $this->getJson('/api/v1/orders/'.$item->shopOrder->order_id)->assertJsonPath('data.shop_orders.0.items.0.can_review', true);
        $review = Review::factory()->for($item)->create(['status' => ReviewStatus::HIDDEN]);
        $this->getJson($path)->assertJsonPath('data.0.can_review', false)->assertJsonPath('data.0.can_edit_review', true)->assertJsonPath('data.0.review.status', 'HIDDEN');
        $this->login(User::factory()->create());
        $this->getJson($path)->assertOk()->assertJsonCount(0, 'data');
        $this->login($item->shopOrder->shop->owner);
        $this->getJson('/api/v1/seller/orders/'.$item->shop_order_id)->assertOk()->assertJsonMissingPath('data.items.0.review')->assertJsonMissingPath('data.items.0.can_review');
    }

    public function test_deleted_author_is_anonymized_while_verified_purchase_remains_visible(): void
    {
        $item = $this->item();
        $review = Review::factory()->for($item)->create();
        $review->user->delete();
        $this->getJson('/api/v1/products/'.$item->product->slug.'/reviews')->assertOk()->assertJsonPath('data.0.author.name', 'Người mua')->assertJsonPath('meta.total', 1);
    }

    public function test_review_list_and_product_summaries_do_not_add_queries_per_row(): void
    {
        $item = $this->item();
        $product = $item->product;
        Review::factory()->for($item)->create();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson('/api/v1/products/'.$product->slug.'/reviews')->assertOk();
        $one = count(DB::getQueryLog());
        for ($i = 0; $i < 5; $i++) {
            Review::factory()->for($this->item($product))->create();
        }
        DB::flushQueryLog();
        $this->getJson('/api/v1/products/'.$product->slug.'/reviews')->assertOk()->assertJsonCount(6, 'data');
        $many = count(DB::getQueryLog());
        $this->assertLessThanOrEqual($one + 1, $many);
        DB::flushQueryLog();
        $this->getJson('/api/v1/products')->assertOk()->assertJsonPath('data.0.review_count', 6);
        $one = count(DB::getQueryLog());
        Product::factory()->active()->count(5)->create();
        DB::flushQueryLog();
        $this->getJson('/api/v1/products')->assertOk();
        $this->assertLessThanOrEqual($one + 1, count(DB::getQueryLog()));
        DB::disableQueryLog();
    }
}
