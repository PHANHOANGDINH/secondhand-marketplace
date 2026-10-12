<?php

namespace Tests\Feature;

use App\Enums\ProductVideoStatus;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\Product;
use App\Models\ProductVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VideoDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_ended_auction_reserved_for_winner_is_not_presented_as_fixed_price(): void
    {
        $auction = Auction::factory()->create();
        $bid = Bid::factory()->for($auction)->create();
        $auction->forceFill(['status' => 'ENDED', 'winner_user_id' => $bid->user_id, 'winning_bid_id' => $bid->id, 'winner_checkout_expires_at' => now()->addHour()])->save();
        $video = ProductVideo::factory()->for($auction->product)->create();
        $this->getJson('/api/v1/videos/'.$video->id)->assertOk()->assertJsonPath('data.auction.status', 'ENDED');
        $auction->forceFill(['winner_checkout_expires_at' => now()->subSecond()])->save();
        $this->getJson('/api/v1/videos/'.$video->id)->assertOk()->assertJsonPath('data.auction', null);
    }

    public function test_public_feed_returns_only_ready_visible_products_with_safe_context(): void
    {
        $product = Product::factory()->active()->create();
        $video = ProductVideo::factory()->for($product)->create();
        ProductVideo::factory()->for($product)->create(['status' => ProductVideoStatus::Hidden, 'is_primary' => false]);
        ProductVideo::factory()->create();
        $this->getJson('/api/v1/videos')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $video->id)
            ->assertJsonPath('data.0.product.shop.name', $product->shop->name)
            ->assertJsonPath('data.0.auction', null)
            ->assertJsonMissingPath('data.0.path')->assertJsonMissingPath('data.0.uploaded_by');
        $this->getJson('/api/v1/videos/'.$video->id)->assertOk()->assertJsonPath('data.product.slug', $product->slug);
    }

    public function test_deep_links_reject_hidden_processing_failed_deleted_and_unavailable_products(): void
    {
        foreach ([ProductVideoStatus::Hidden, ProductVideoStatus::Processing, ProductVideoStatus::Failed] as $status) {
            $v = ProductVideo::factory()->for(Product::factory()->active())->create(['status' => $status, 'is_primary' => false]);
            $this->getJson('/api/v1/videos/'.$v->id)->assertNotFound();
        }
        foreach (['hidden', 'stock', 'deleted', 'shop', 'owner', 'category'] as $reason) {
            $p = Product::factory()->active()->create();
            $v = ProductVideo::factory()->for($p)->create();
            match ($reason) {
                'hidden' => $p->update(['status' => 'HIDDEN']),
                'stock' => $p->update(['stock_quantity' => 0]),
                'deleted' => $p->delete(),
                'shop' => $p->shop->update(['status' => 'SUSPENDED']),
                'owner' => $p->shop->owner->forceFill(['status' => 'SUSPENDED'])->save(),
                'category' => $p->category->update(['status' => 'INACTIVE']),
            };
            $this->getJson('/api/v1/videos/'.$v->id)->assertNotFound();
        }
        $this->getJson('/api/v1/videos')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/videos/999999999')->assertNotFound();
    }

    public function test_feed_is_paginated_and_validates_page_size(): void
    {
        $p = Product::factory()->active()->create();
        ProductVideo::factory()->count(3)->for($p)->create(['is_primary' => false]);
        $first = $this->getJson('/api/v1/videos?per_page=2')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 3)->json('data.0.id');
        $second = $this->getJson('/api/v1/videos?per_page=2&page=2')->assertOk()->assertJsonCount(1, 'data')->json('data.0.id');
        $this->assertNotSame($first, $second);
        $this->getJson('/api/v1/videos?per_page=25')->assertUnprocessable();
        $this->getJson('/api/v1/videos?page=0')->assertUnprocessable();
        $this->getJson('/api/v1/videos?status=HIDDEN')->assertUnprocessable();
    }

    public function test_auction_context_is_real_and_eager_loaded_without_per_card_queries(): void
    {
        $p = Product::factory()->active()->create();
        $auction = Auction::factory()->for($p)->create();
        ProductVideo::factory()->count(3)->for($p)->create(['is_primary' => false]);
        DB::enableQueryLog();
        $this->getJson('/api/v1/videos')->assertOk()->assertJsonPath('data.0.auction.id', (string) $auction->id)->assertJsonPath('data.0.auction.current_price', '100.00');
        $queries = count(DB::getQueryLog());
        DB::flushQueryLog();
        foreach (range(1, 4) as $i) {
            ProductVideo::factory()->for(Product::factory()->active())->create();
        }
        DB::flushQueryLog();
        $this->getJson('/api/v1/videos')->assertOk()->assertJsonCount(7, 'data');
        $this->assertSame($queries, count(DB::getQueryLog()));
        DB::disableQueryLog();
        $auction->forceFill(['status' => 'CANCELLED'])->save();
        $this->getJson('/api/v1/videos/'.$p->videos()->first()->id)->assertJsonPath('data.auction', null);
    }
}
