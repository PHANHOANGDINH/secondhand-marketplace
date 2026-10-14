<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\AuctionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SellerAdCountsTest extends TestCase
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

    public function test_counts_cover_every_status_and_auction_holds_for_the_sellers_own_shop_in_one_query(): void
    {
        $shop = Shop::factory()->create();
        $make = fn (string $status, array $extra = []): Product => Product::factory()->create(['shop_id' => $shop->id, 'status' => $status, ...$extra]);
        $active = $make('ACTIVE');
        $make('ACTIVE');
        $make('SOLD', ['stock_quantity' => 0]);
        $make('REJECTED');
        $make('DRAFT');
        $make('DRAFT');
        $make('DRAFT');
        $make('PENDING');
        $make('HIDDEN');
        $make('ACTIVE')->delete();
        // Held by a running auction, and by an ended one whose winner can still check out.
        Auction::factory()->create(['product_id' => $active->id]);
        $awaiting = $make('ACTIVE');
        $ended = Auction::factory()->create(['product_id' => $awaiting->id]);
        app(AuctionService::class)->placeBid(User::factory()->create(), $ended, '110.00');
        $ended->forceFill(['ends_at' => now()->subSecond()])->save();
        app(AuctionService::class)->finalize($ended);
        // An auction that no longer holds the product does not count.
        Auction::factory()->create(['product_id' => $make('ACTIVE')->id, 'status' => 'CANCELLED']);
        // Another shop's products never leak into the numbers.
        Product::factory()->active()->count(3)->create();
        Auction::factory()->create();

        $this->getJson('/api/v1/seller/products/counts')->assertUnauthorized();
        $this->login(User::factory()->create());
        $this->getJson('/api/v1/seller/products/counts')->assertForbidden();

        $this->login($shop->owner);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->getJson('/api/v1/seller/products/counts')->assertOk();
        DB::disableQueryLog();
        $response->assertExactJson(['data' => ['DRAFT' => 3, 'PENDING' => 1, 'ACTIVE' => 4, 'SOLD' => 1, 'HIDDEN' => 1, 'REJECTED' => 1, 'AUCTION' => 2, 'ALL' => 11]]);
        $this->assertCount(1, array_filter(DB::getQueryLog(), fn (array $query): bool => str_contains($query['query'], 'from `products`')));
        $this->getJson('/api/v1/seller/products/counts?shop_id=1')->assertUnprocessable();

        // The list filter behind the "Đang đấu giá" tab returns exactly the held products.
        $held = $this->getJson('/api/v1/seller/products?auction=1')->assertOk();
        $this->assertEqualsCanonicalizing([(string) $active->id, (string) $awaiting->id], $held->json('data.*.id'));
        $held->assertJsonPath('data.0.auction_locked', true);
        $this->assertCount(4, $this->getJson('/api/v1/seller/products?status=ACTIVE')->assertOk()->json('data'));
        $this->assertCount(11, $this->getJson('/api/v1/seller/products?auction=0&per_page=100')->assertOk()->json('data'));
        $this->getJson('/api/v1/seller/products?auction=maybe')->assertUnprocessable();
    }

    public function test_an_empty_shop_gets_zero_for_every_counter(): void
    {
        $this->login(Shop::factory()->create()->owner);
        $this->getJson('/api/v1/seller/products/counts')->assertOk()
            ->assertExactJson(['data' => ['DRAFT' => 0, 'PENDING' => 0, 'ACTIVE' => 0, 'SOLD' => 0, 'HIDDEN' => 0, 'REJECTED' => 0, 'AUCTION' => 0, 'ALL' => 0]]);
    }
}
