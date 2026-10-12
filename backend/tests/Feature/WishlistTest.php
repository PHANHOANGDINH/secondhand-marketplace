<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\Product;
use App\Models\ProductVideo;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WishlistTest extends TestCase
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

    public function test_guest_cannot_list_add_or_remove(): void
    {
        $p = Product::factory()->active()->create();
        $this->getJson('/api/v1/wishlist')->assertUnauthorized();
        $this->postJson('/api/v1/products/'.$p->id.'/wishlist')->assertUnauthorized();
        $this->deleteJson('/api/v1/products/'.$p->id.'/wishlist')->assertUnauthorized();
        $this->getJson('/api/v1/products')->assertOk()->assertJsonPath('data.0.is_wishlisted', false);
    }

    public function test_add_is_idempotent_and_preserves_stock_price_and_saved_date(): void
    {
        $p = Product::factory()->active()->create();
        $before = $p->fresh()->getRawOriginal();
        $this->login(User::factory()->create());
        $path = '/api/v1/products/'.$p->id.'/wishlist';
        $this->postJson($path)->assertOk()->assertJsonPath('data.is_wishlisted', true);
        $saved = Wishlist::sole()->created_at;
        $this->travel(1)->minutes();
        $this->postJson($path)->assertOk();
        $this->assertDatabaseCount('wishlists', 1);
        $this->assertEquals($saved, Wishlist::sole()->created_at);
        $this->assertSame($before, $p->fresh()->getRawOriginal());
        $this->deleteJson($path)->assertOk()->assertJsonPath('data.is_wishlisted', false);
        $this->deleteJson($path)->assertOk();
        $this->assertDatabaseCount('wishlists', 0);
    }

    public function test_only_own_records_are_listed_or_removed_and_client_cannot_choose_user(): void
    {
        $other = Wishlist::factory()->create();
        $actor = User::factory()->create();
        $this->login($actor);
        $this->getJson('/api/v1/wishlist')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/wishlist?user_id='.$other->user_id)->assertUnprocessable();
        $this->deleteJson('/api/v1/products/'.$other->product_id.'/wishlist')->assertOk();
        $this->assertDatabaseHas('wishlists', ['id' => $other->id]);
        $this->postJson('/api/v1/products/'.$other->product_id.'/wishlist', ['user_id' => $other->user_id])->assertUnprocessable();
        $this->getJson('/api/v1/products')->assertOk()->assertJsonPath('data.0.is_wishlisted', false);
    }

    public function test_listing_is_newest_saved_first_paginated_and_redacted(): void
    {
        $actor = User::factory()->create();
        $old = Wishlist::factory()->create(['user_id' => $actor->id, 'created_at' => now()->subDay()]);
        $new = Wishlist::factory()->create(['user_id' => $actor->id]);
        $this->login($actor);
        $this->getJson('/api/v1/wishlist?per_page=1')->assertOk()->assertJsonPath('data.0.id', (string) $new->product_id)->assertJsonPath('data.0.is_wishlisted', true)->assertJsonPath('meta.total', 2)->assertJsonMissingPath('data.0.shop.owner_id')->assertJsonMissingPath('data.0.moderation_reason');
        $this->getJson('/api/v1/wishlist?per_page=1&page=2')->assertOk()->assertJsonPath('data.0.id', (string) $old->product_id);
        foreach (['page=0', 'per_page=101', 'per_page[]=bad'] as $query) {
            $this->getJson('/api/v1/wishlist?'.$query)->assertUnprocessable();
        }
    }

    public function test_unavailable_saved_products_are_filtered_and_can_still_be_removed(): void
    {
        $actor = User::factory()->create();
        $this->login($actor);
        foreach (['hidden', 'deleted', 'shop', 'category', 'owner', 'stock'] as $case) {
            $p = Product::factory()->active()->create();
            $this->postJson('/api/v1/products/'.$p->id.'/wishlist')->assertOk();
            match ($case) {
                'hidden' => $p->update(['status' => 'HIDDEN']),
                'deleted' => $p->delete(),
                'shop' => $p->shop->update(['status' => 'SUSPENDED']),
                'category' => $p->category->update(['status' => 'INACTIVE']),
                'owner' => $p->shop->owner->forceFill(['status' => 'SUSPENDED'])->save(),
                'stock' => $p->update(['stock_quantity' => 0]),
            };
            $this->getJson('/api/v1/wishlist')->assertOk()->assertJsonCount(0, 'data');
            $this->postJson('/api/v1/products/'.$p->id.'/wishlist')->assertNotFound();
            $this->deleteJson('/api/v1/products/'.$p->id.'/wishlist')->assertOk();
        }
    }

    public function test_admin_and_seller_may_save_public_products_including_own_auction(): void
    {
        $auction = Auction::factory()->create();
        $product = $auction->product;
        $before = $auction->fresh()->getRawOriginal();
        foreach ([User::factory()->admin()->create(), $product->shop->owner] as $user) {
            $this->login($user);
            $this->postJson('/api/v1/products/'.$product->id.'/wishlist')->assertOk();
            $this->getJson('/api/v1/wishlist')->assertOk()->assertJsonPath('data.0.auction.id', (string) $auction->id);
        }
        $this->assertSame($before, $auction->fresh()->getRawOriginal());
    }

    public function test_invalid_product_and_suspended_account_are_rejected(): void
    {
        $actor = User::factory()->create();
        $this->login($actor);
        foreach (['9999999', 'abc', '0', '-1'] as $id) {
            $this->postJson('/api/v1/products/'.$id.'/wishlist')->assertNotFound();
        }
        $actor->forceFill(['status' => 'SUSPENDED'])->save();
        $this->getJson('/api/v1/wishlist')->assertForbidden();
        $this->postJson('/api/v1/products/1/wishlist')->assertForbidden();
    }

    public function test_database_uniqueness_constraint_is_enforced(): void
    {
        $saved = Wishlist::factory()->create();
        $this->expectException(QueryException::class);
        Wishlist::create(['user_id' => $saved->user_id, 'product_id' => $saved->product_id]);
    }

    public function test_annotations_cover_catalog_detail_suggestions_and_video_without_n_plus_one(): void
    {
        $saved = Wishlist::factory()->create();
        $this->login(User::findOrFail($saved->user_id));
        $p = Product::findOrFail($saved->product_id);
        $video = ProductVideo::factory()->create(['product_id' => $p->id]);
        foreach (['/products', '/products/'.$p->slug] as $path) {
            $this->getJson('/api/v1'.$path)->assertOk()->assertJsonPath($path === '/products' ? 'data.0.is_wishlisted' : 'data.is_wishlisted', true);
        }
        $this->getJson('/api/v1/products/suggestions?q='.rawurlencode(mb_substr($p->name, 0, 5)))->assertOk()->assertJsonPath('data.products.0.is_wishlisted', true);
        $this->getJson('/api/v1/videos/'.$video->id)->assertOk()->assertJsonPath('data.product.is_wishlisted', true);
        DB::enableQueryLog();
        $this->getJson('/api/v1/products')->assertOk();
        $one = count(DB::getQueryLog());
        DB::disableQueryLog();
        Product::factory()->active()->count(5)->create();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/v1/products')->assertOk()->assertJsonCount(6, 'data');
        $many = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThanOrEqual($one + 1, $many);
    }
}
