<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\Product;
use App\Models\ProductVideo;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    private function ids(array $query = []): array
    {
        return $this->getJson('/api/v1/products?'.http_build_query($query))->assertOk()->json('data.*.id');
    }

    public function test_keyword_aliases_search_public_shop_category_and_literal_input(): void
    {
        $p = Product::factory()->active()->create(['name' => 'Unique 50%_!', 'description' => 'Description token']);
        $p->shop->update(['name' => 'Gian hàng đặc biệt']);
        $p->category->update(['name' => 'Danh mục đặc biệt']);
        foreach (['q', 'search', 'keyword'] as $key) {
            foreach (['50%_!', 'Description token', 'Gian hàng đặc biệt', 'Danh mục đặc biệt'] as $keyword) {
                $this->assertSame([(string) $p->id], $this->ids([$key => $keyword]));
            }
        }
        $this->assertSame([], $this->ids(['q' => "' OR 1=1 --"]));
    }

    public function test_video_sale_type_and_all_filters_compose_without_unlocking_auction(): void
    {
        $p = Product::factory()->active()->create(['name' => 'Search target', 'price' => '123.45', 'condition' => 'GOOD']);
        ProductVideo::factory()->create(['product_id' => $p->id]);
        $auction = Auction::factory()->create(['product_id' => $p->id]);
        $fixed = Product::factory()->active()->create();
        ProductVideo::factory()->create(['product_id' => $fixed->id, 'status' => 'FAILED', 'is_primary' => false]);
        $query = ['q' => 'Search target', 'category' => $p->category_id, 'shop' => $p->shop_id, 'condition' => 'GOOD', 'min_price' => '123.45', 'max_price' => '123.45', 'has_video' => '1', 'sale_type' => 'auction'];
        $this->assertSame([(string) $p->id], $this->ids($query));
        $this->assertSame([], $this->ids([...$query, 'has_video' => '0']));
        $this->assertSame([(string) $fixed->id], $this->ids(['sale_type' => 'fixed', 'has_video' => '0']));
        $this->getJson('/api/v1/products?sale_type=auction')->assertOk()->assertJsonPath('data.0.auction.id', (string) $auction->id);
    }

    public function test_oldest_relevance_and_pagination_preserve_filters(): void
    {
        $a = Product::factory()->active()->create(['name' => 'Device', 'created_at' => now()->subDays(2)]);
        $b = Product::factory()->active()->create(['name' => 'Device accessory', 'created_at' => now()->subDay()]);
        $c = Product::factory()->active()->create(['name' => 'Other', 'description' => 'Device', 'created_at' => now()]);
        $ids = [(string) $a->id, (string) $b->id, (string) $c->id];
        $this->assertSame($ids, $this->ids(['q' => 'Device', 'sort' => 'relevance']));
        $this->assertSame($ids, $this->ids(['sort' => 'oldest']));
        $response = $this->getJson('/api/v1/products?q=Device&sort=relevance&per_page=1&page=2')->assertOk()->assertJsonPath('data.0.id', (string) $b->id)->assertJsonPath('meta.total', 3);
        parse_str(parse_url($response->json('links.next'), PHP_URL_QUERY), $query);
        $this->assertSame('Device', $query['q']);
        $this->assertSame('relevance', $query['sort']);
        $this->assertSame('3', $query['page']);
    }

    public function test_rating_uses_only_valid_published_purchase_aggregates(): void
    {
        $high = Review::factory()->create(['rating' => 5]);
        $low = Review::factory()->create(['rating' => 2]);
        Review::factory()->create(['rating' => 5, 'status' => 'HIDDEN']);
        $rows = $this->getJson('/api/v1/products?sort=rating')->assertOk()->json('data');
        $this->assertSame((string) $high->product_id, $rows[0]['id']);
        $this->assertSame((string) $low->product_id, $rows[1]['id']);
        $this->assertEquals(5, $rows[0]['average_rating']);
    }

    public function test_invalid_new_parameters_are_rejected(): void
    {
        foreach (['has_video=anything', 'sale_type=unknown', 'q[]=bad', 'q=a&search=b', 'q=a&keyword=b', 'min_price=1e2', 'max_price=-1', 'sort=rating;DROP TABLE users'] as $query) {
            $this->getJson('/api/v1/products?'.$query)->assertUnprocessable();
        }
        foreach (['', 'q=x', 'q=%20%20', 'q[]=xx', 'q=xx&status=ACTIVE'] as $query) {
            $this->getJson('/api/v1/products/suggestions?'.$query)->assertUnprocessable();
        }
    }

    public function test_zero_keyword_is_ranked_and_not_treated_as_empty(): void
    {
        $exact = Product::factory()->active()->create(['name' => '0', 'created_at' => now()->subDay()]);
        $partial = Product::factory()->active()->create(['name' => 'Device 0', 'created_at' => now()]);
        $this->assertSame([(string) $exact->id, (string) $partial->id], $this->ids(['q' => '0', 'sort' => 'relevance']));
    }

    public function test_suggestions_only_return_public_data_and_are_bounded(): void
    {
        $p = Product::factory()->active()->create(['name' => 'Find device']);
        $p->shop->update(['name' => 'Find shop']);
        $p->category->update(['name' => 'Find category']);
        Product::factory()->create(['name' => 'Find hidden', 'status' => 'HIDDEN']);
        Product::factory()->active()->count(8)->create(['name' => 'Find other']);
        $this->getJson('/api/v1/products/suggestions?q=Find')->assertOk()->assertJsonCount(5, 'data.products')->assertJsonPath('data.shops.0.name', 'Find shop')->assertJsonPath('data.categories.0.name', 'Find category')->assertJsonMissingPath('data.products.0.moderation_reason');
        $p->shop->update(['status' => 'SUSPENDED']);
        $this->getJson('/api/v1/products/suggestions?q=Find shop')->assertOk()->assertJsonCount(0, 'data.products')->assertJsonCount(0, 'data.shops');
        $this->assertSame([], $this->ids(['q' => 'Find shop']));
    }

    public function test_new_filters_do_not_add_per_product_queries(): void
    {
        ProductVideo::factory()->create(['product_id' => Product::factory()->active()->create()->id]);
        DB::enableQueryLog();
        $this->getJson('/api/v1/products?has_video=1&sort=rating')->assertOk();
        $one = count(DB::getQueryLog());
        DB::disableQueryLog();
        foreach (range(1, 5) as $index) {
            ProductVideo::factory()->create(['product_id' => Product::factory()->active()->create()->id]);
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/v1/products?has_video=1&sort=rating')->assertOk()->assertJsonCount(6, 'data');
        $many = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThanOrEqual($one + 1, $many);
    }
}
