<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductView;
use App\Models\Review;
use App\Models\SearchLog;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RecommendationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeaders(['Origin' => 'http://localhost:5173', 'Accept' => 'application/json']);
        $this->freezeTime();
    }

    private function login(?User $user): void
    {
        $this->app['session']->flush();
        Auth::forgetGuards();
        if ($user) {
            $this->actingAs($user, 'web');
        }
    }

    private function product(array $attributes = []): Product
    {
        return Product::factory()->active()->create($attributes);
    }

    private function category(string $name, ?Category $parent = null): Category
    {
        return Category::factory()->create(['name' => $name, 'parent_id' => $parent?->id]);
    }

    private function viewed(User $user, Product $product): void
    {
        ProductView::create(['user_id' => $user->id, 'product_id' => $product->id, 'viewed_at' => now()]);
    }

    /** @return list<string> */
    private function ids(string $path = '/api/v1/recommendations'): array
    {
        return $this->getJson($path)->assertOk()->json('data.*.id');
    }

    public function test_order_is_deterministic_and_explained_for_a_fixture(): void
    {
        $root = $this->category('Điện tử');
        $phones = $this->category('Điện thoại', $root);
        $tablets = $this->category('Máy tính bảng', $root);
        $seen = $this->product(['category_id' => $phones->id, 'name' => 'Máy A']);
        $otherA = $this->product(['category_id' => $this->category('Sách')->id, 'name' => 'Món C']);
        $otherB = $this->product(['category_id' => $this->category('Nội thất')->id, 'name' => 'Món D']);
        $sibling = $this->product(['category_id' => $tablets->id, 'name' => 'Máy B']);
        $same = $this->product(['category_id' => $phones->id, 'name' => 'Máy E']);
        $user = User::factory()->create();
        $this->viewed($user, $seen);
        $this->login($user);

        // Same shop and category as the view, same category, sibling category, then unrelated products by id.
        $expected = array_map(fn (Product $p): string => (string) $p->id, [$seen, $same, $sibling, $otherA, $otherB]);
        $response = $this->getJson('/api/v1/recommendations')->assertOk();
        $this->assertSame($expected, $response->json('data.*.id'));
        $this->assertSame(
            ['Vì bạn quan tâm Điện thoại', 'Vì bạn quan tâm Điện thoại', 'Vì bạn quan tâm Điện thoại', 'Mới đăng gần đây', 'Mới đăng gần đây'],
            $response->json('data.*.reason_label'),
        );
        foreach (range(1, 3) as $attempt) {
            Cache::flush();
            $this->assertSame($expected, $this->ids());
        }
        $this->assertSame(array_slice($expected, 0, 2), $this->ids('/api/v1/recommendations?limit=2'));
        foreach (['limit=0', 'limit=25', 'limit[]=1', 'user_id=1'] as $query) {
            $this->getJson('/api/v1/recommendations?'.$query)->assertUnprocessable();
        }
    }

    public function test_wishlists_and_orders_weigh_more_than_views_and_parent_category_counts(): void
    {
        $root = $this->category('Thời trang');
        $shoes = $this->category('Giày', $root);
        $viewedOnly = $this->product(['category_id' => $this->category('Đồng hồ')->id]);
        $inRoot = $this->product(['category_id' => $root->id]);
        $user = User::factory()->create();
        $this->viewed($user, $viewedOnly);
        Wishlist::factory()->create(['user_id' => $user->id, 'product_id' => $this->product(['category_id' => $shoes->id, 'status' => 'HIDDEN'])->id]);
        $this->login($user);
        // A wishlist in "Giày" (3 points, half of it reaches the parent) beats one view in "Đồng hồ" only through weights:
        // parent product gets 1.5 category points, the viewed product 1 category point plus 1 shop point.
        $response = $this->getJson('/api/v1/recommendations')->assertOk();
        $this->assertSame([(string) $inRoot->id, (string) $viewedOnly->id], $response->json('data.*.id'));
        $this->assertSame('Vì bạn quan tâm Giày', $response->json('data.0.reason_label'));

        Cache::flush();
        $bought = $this->product(['category_id' => $this->category('Máy ảnh')->id]);
        $order = ShopOrder::factory()->create(['shop_id' => $bought->shop_id]);
        $order->order->update(['buyer_id' => $user->id]);
        OrderItem::factory()->create(['shop_order_id' => $order->id, 'product_id' => $bought->id]);
        $this->assertSame((string) $bought->id, $this->ids()[0]);
    }

    public function test_recent_search_keywords_lift_matching_products(): void
    {
        $match = $this->product(['name' => 'iPhone 12 cũ', 'category_id' => $this->category('Khác')->id]);
        $newer = $this->product(['name' => 'Bàn gỗ', 'category_id' => $this->category('Bàn ghế')->id]);
        $user = User::factory()->create();
        $this->login($user);
        $this->assertSame([(string) $match->id, (string) $newer->id], $this->ids());
        $this->getJson('/api/v1/products?q=IPHONE')->assertOk();
        Cache::flush();
        $response = $this->getJson('/api/v1/recommendations')->assertOk();
        $this->assertSame((string) $match->id, $response->json('data.0.id'));
        $this->assertSame('Vì bạn đã tìm “IPHONE”', $response->json('data.0.reason_label'));
        $this->assertSame('Mới đăng gần đây', $response->json('data.1.reason_label'));
    }

    public function test_own_shop_non_public_and_sold_products_are_excluded(): void
    {
        $user = User::factory()->create();
        $shop = Shop::factory()->create(['owner_id' => $user->id]);
        $own = $this->product(['shop_id' => $shop->id]);
        $visible = $this->product();
        $this->product(['status' => 'HIDDEN']);
        $this->product(['status' => 'SOLD', 'stock_quantity' => 0]);
        $this->product(['status' => 'SOLD']);
        $this->product(['stock_quantity' => 0]);
        $this->product(['status' => 'PENDING']);
        $this->product()->delete();
        $this->product()->shop->update(['status' => 'SUSPENDED']);
        $this->product()->category->update(['status' => 'INACTIVE']);
        $this->viewed($user, $visible);
        $this->login($user);
        $this->assertSame([(string) $visible->id], $this->ids());
        // The seller's own product is still public for everyone else.
        $this->login(null);
        $this->assertEqualsCanonicalizing([(string) $own->id, (string) $visible->id], $this->ids());
    }

    public function test_cached_ranking_drops_products_that_stop_being_public(): void
    {
        $first = $this->product();
        $second = $this->product();
        $this->assertCount(2, $this->ids());
        $second->update(['status' => 'HIDDEN']);
        $this->assertSame([(string) $first->id], $this->ids());
    }

    public function test_cold_start_uses_only_popularity_and_recency(): void
    {
        $category = $this->category('Điện thoại');
        $old = $this->product(['category_id' => $category->id, 'published_at' => now()->subDays(60), 'created_at' => now()->subDays(60)]);
        $fresh = $this->product(['category_id' => $category->id]);
        $popular = $this->product(['category_id' => $category->id, 'published_at' => now()->subDays(3)]);
        Wishlist::factory()->count(3)->create(['product_id' => $popular->id]);
        // A five-star review would matter for a known user, but not in a cold start.
        $item = OrderItem::factory()->create(['shop_order_id' => ShopOrder::factory()->completed()->create(['shop_id' => $old->shop_id])->id, 'product_id' => $old->id, 'created_at' => now()->subDays(40)]);
        Review::factory()->create(['order_item_id' => $item->id]);
        $expected = [(string) $popular->id, (string) $fresh->id, (string) $old->id];
        $guest = $this->getJson('/api/v1/recommendations')->assertOk();
        $this->assertSame($expected, array_slice($guest->json('data.*.id'), 0, 3));
        $this->assertSame(['Đang được nhiều người quan tâm', 'Mới đăng gần đây'], array_slice($guest->json('data.*.reason_label'), 0, 2));
        $this->assertSame('Có thể bạn sẽ thích', collect($guest->json('data'))->firstWhere('id', (string) $old->id)['reason_label']);
        $this->assertFalse($guest->json('data.0.is_wishlisted'));

        $this->login(User::factory()->create());
        $this->assertSame($expected, array_slice($this->ids(), 0, 3));
    }

    public function test_product_views_are_logged_once_per_window_for_signed_in_users_only(): void
    {
        $product = $this->product();
        $path = '/api/v1/products/'.$product->slug;
        $this->getJson($path)->assertOk();
        $this->assertDatabaseCount('product_views', 0);

        $user = User::factory()->create();
        $this->login($user);
        $this->getJson($path)->assertOk();
        $this->getJson($path)->assertOk();
        $this->travel(29)->minutes();
        $this->getJson($path)->assertOk();
        $this->assertDatabaseCount('product_views', 1);
        $this->travel(2)->minutes();
        $this->getJson($path)->assertOk();
        $this->assertSame(2, ProductView::where('user_id', $user->id)->where('product_id', $product->id)->count());
        $this->getJson('/api/v1/products/'.$this->product()->slug)->assertOk();
        $this->assertDatabaseCount('product_views', 3);

        $this->login($product->shop->owner);
        $this->getJson($path)->assertOk();
        $this->assertDatabaseCount('product_views', 3);
        $this->getJson('/api/v1/products/missing-product')->assertNotFound();
        $this->assertSame(['id', 'user_id', 'product_id', 'viewed_at'], Schema::getColumnListing('product_views'));
        $this->assertSame(['id', 'user_id', 'query', 'created_at'], Schema::getColumnListing('search_logs'));
    }

    public function test_searches_are_logged_for_signed_in_users_only(): void
    {
        $this->product(['name' => 'Laptop cũ']);
        $this->getJson('/api/v1/products?q=laptop')->assertOk()->assertJsonCount(1, 'data');
        $this->assertDatabaseCount('search_logs', 0);

        $user = User::factory()->create();
        $this->login($user);
        $this->getJson('/api/v1/products')->assertOk();
        $this->getJson('/api/v1/products?q=')->assertOk();
        $this->getJson('/api/v1/products?category=999999')->assertUnprocessable();
        $this->assertDatabaseCount('search_logs', 0);
        $this->getJson('/api/v1/products?q=%20%20laptop%20')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/products?q=laptop&page=2')->assertOk();
        $this->getJson('/api/v1/products/suggestions?q=laptop')->assertOk();
        $this->assertSame(['laptop'], SearchLog::where('user_id', $user->id)->pluck('query')->all());
        $this->getJson('/api/v1/products?q='.str_repeat('a', 101))->assertUnprocessable();
        $this->getJson('/api/v1/products?q='.str_repeat('a', 100))->assertOk();
        $this->assertSame(100, mb_strlen(SearchLog::latest('id')->value('query')));
    }

    public function test_query_count_does_not_grow_with_the_number_of_products(): void
    {
        $user = User::factory()->create();
        $category = $this->category('Điện thoại');
        $this->viewed($user, $this->product(['category_id' => $category->id]));
        $this->product(['category_id' => $category->id]);
        $this->login($user);
        $this->getJson('/api/v1/products?q=phone')->assertOk();
        $count = function (string $path): int {
            Cache::flush();
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson($path)->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };
        $slug = Product::first()->slug;
        $few = [$count('/api/v1/recommendations?limit=24'), $count('/api/v1/products/'.$slug.'/similar')];
        Product::factory()->active()->count(12)->create(['category_id' => $category->id]);
        Product::factory()->active()->count(12)->create();
        Wishlist::factory()->count(4)->create();
        $this->assertCount(24, $this->ids('/api/v1/recommendations?limit=24'));
        $this->assertSame($few, [$count('/api/v1/recommendations?limit=24'), $count('/api/v1/products/'.$slug.'/similar')]);
    }

    public function test_cache_is_per_user_and_never_leaks_to_another_user_or_guest(): void
    {
        $phones = $this->category('Điện thoại');
        $liked = $this->product(['category_id' => $phones->id, 'published_at' => now()->subDays(40)]);
        $recent = $this->product(['category_id' => $this->category('Sách')->id]);
        $fan = User::factory()->create();
        $other = User::factory()->create();
        Wishlist::factory()->create(['user_id' => $fan->id, 'product_id' => $liked->id]);

        $this->login($fan);
        $mine = $this->getJson('/api/v1/recommendations')->assertOk();
        $this->assertSame((string) $liked->id, $mine->json('data.0.id'));
        $this->assertSame('Vì bạn quan tâm Điện thoại', $mine->json('data.0.reason_label'));
        $this->assertTrue($mine->json('data.0.is_wishlisted'));

        foreach ([$other, null] as $viewer) {
            $this->login($viewer);
            $theirs = $this->getJson('/api/v1/recommendations')->assertOk();
            $this->assertSame((string) $recent->id, $theirs->json('data.0.id'));
            $this->assertNotContains('Vì bạn quan tâm Điện thoại', $theirs->json('data.*.reason_label'));
            $this->assertNotContains(true, $theirs->json('data.*.is_wishlisted'));
        }

        // Within ten minutes the ranking is reused, but is_wishlisted is read live.
        $this->login($fan);
        $this->viewed($fan, $recent);
        Wishlist::where('user_id', $fan->id)->delete();
        $cached = $this->getJson('/api/v1/recommendations')->assertOk();
        $this->assertSame($mine->json('data.*.id'), $cached->json('data.*.id'));
        $this->assertFalse($cached->json('data.0.is_wishlisted'));
        $this->travel(11)->minutes();
        $this->assertSame((string) $recent->id, $this->ids()[0]);
    }

    public function test_similar_products_share_category_or_parent_within_half_price_and_prefer_the_same_shop(): void
    {
        $root = $this->category('Điện tử');
        $phones = $this->category('Điện thoại', $root);
        $product = $this->product(['category_id' => $phones->id, 'price' => '100000.00']);
        $inParent = $this->product(['category_id' => $root->id, 'price' => '100000.00']);
        $edge = $this->product(['category_id' => $phones->id, 'price' => '150000.00']);
        $close = $this->product(['category_id' => $phones->id, 'price' => '110000.00']);
        $sameShop = $this->product(['category_id' => $phones->id, 'price' => '50000.00', 'shop_id' => $product->shop_id]);
        $this->product(['category_id' => $phones->id, 'price' => '150000.01']);
        $this->product(['category_id' => $phones->id, 'price' => '49999.99']);
        $this->product(['category_id' => $this->category('Máy tính bảng', $root)->id, 'price' => '100000.00']);
        $this->product(['category_id' => $phones->id, 'price' => '100000.00', 'status' => 'HIDDEN']);
        $this->product(['category_id' => $phones->id, 'price' => '100000.00', 'status' => 'SOLD', 'stock_quantity' => 0]);

        $path = '/api/v1/products/'.$product->slug.'/similar';
        $expected = array_map(fn (Product $p): string => (string) $p->id, [$sameShop, $close, $edge, $inParent]);
        $this->assertSame($expected, $this->ids($path));
        $this->assertSame(array_slice($expected, 0, 2), $this->ids($path.'?limit=2'));
        $this->getJson($path)->assertJsonMissingPath('data.0.reason_label')->assertJsonPath('data.0.is_wishlisted', false)->assertJsonMissingPath('data.0.shop.owner_id');
        $this->getJson($path.'?limit=0')->assertUnprocessable();
        $this->assertDatabaseCount('product_views', 0);

        $user = User::factory()->create();
        Wishlist::factory()->create(['user_id' => $user->id, 'product_id' => $close->id]);
        $this->login($user);
        $this->getJson($path)->assertOk()->assertJsonPath('data.1.is_wishlisted', true);
        $product->update(['status' => 'HIDDEN']);
        $this->getJson($path)->assertNotFound();
    }
}
