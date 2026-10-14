<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVideo;
use App\Models\Report;
use App\Models\Review;
use App\Models\Shop;
use App\Models\User;
use App\Models\Voucher;
use App\Services\AdminActionCenterService;
use App\Services\ProductQuery;
use App\Services\RecommendationService;
use App\Services\ReviewService;
use App\Services\SellerInventoryService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Database\Seeders\FinalDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class FinalDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.password' => 'FictionalTestOnlyC1!', 'broadcasting.default' => 'null']);
        Storage::fake('public');
    }

    private function snapshot(): array
    {
        $result = [];
        foreach (['users', 'shops', 'categories', 'products', 'product_images', 'addresses', 'auctions', 'bids', 'orders', 'shop_orders', 'order_items', 'payments', 'reviews', 'notifications', 'product_videos', 'video_comments', 'conversations', 'conversation_participants', 'messages', 'audit_logs', 'carts', 'cart_items', 'wishlists', 'reports'] as $table) {
            $result[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return $result;
    }

    public function test_pack_is_valid_additive_repeatable_and_restores_clock(): void
    {
        $unrelated = User::factory()->create();
        $before = $unrelated->fresh()->getRawOriginal();
        $old = CarbonImmutable::now()->toISOString();
        $this->seed(FinalDemoSeeder::class);
        $this->assertSame($before, $unrelated->fresh()->getRawOriginal());
        $this->assertSame(7, User::count());
        $this->assertDatabaseCount('wishlists', 4);
        $this->assertDatabaseCount('reports', 3);
        $this->assertSame(['DISMISSED' => 1, 'IN_REVIEW' => 1, 'PENDING' => 1], Report::select('status', DB::raw('COUNT(*) AS total'))->groupBy('status')->orderBy('status')->pluck('total', 'status')->all());
        $this->assertSame(2, Shop::where('status', 'ACTIVE')->count());
        $this->assertSame(['79', '01'], Shop::where('name', 'like', 'C1 Demo%')->orderBy('id')->pluck('province_code')->all());
        $inventoryShop = Shop::where('owner_id', User::where('email', 'c1-seller-1@example.test')->sole()->id)->sole();
        $summary = app(SellerInventoryService::class)->summary($inventoryShop);
        $this->assertGreaterThan(0, $summary['low_stock_products']);
        $this->assertGreaterThan(0, $summary['out_of_stock_products']);
        $this->assertGreaterThan(0, $summary['pending_products']);
        $this->assertGreaterThan(0, $summary['rejected_products']);
        $this->assertGreaterThan(0, $summary['orders_requiring_action']);
        $this->assertGreaterThan(0, $summary['live_auctions']);
        $this->assertSame(22, Product::count());
        $this->assertSame(18, app(ProductQuery::class)->publicVisible()->count());
        $this->assertSame(['CANCELLED' => 1, 'ENDED' => 2, 'LIVE' => 2, 'SCHEDULED' => 1], Auction::select('status', DB::raw('COUNT(*) AS total'))->groupBy('status')->orderBy('status')->pluck('total', 'status')->all());
        $ended = Auction::whereNotNull('winner_order_id')->sole();
        $order = Order::findOrFail($ended->winner_order_id);
        $this->assertSame($ended->winner_user_id, $order->buyer_id);
        $this->assertSame('120000.00', $order->total_amount);
        $this->assertSame($ended->id, $order->shopOrders->sole()->orderItems->sole()->auction_id);
        $this->assertSame('COMPLETED', $order->shopOrders->sole()->status->value);
        $this->assertSame(7, Order::count());
        // D8 additions: one payment waiting for reconciliation, an admin-cancellable auction, view and search signals.
        $this->assertSame(1, Payment::where('reconciliation_required', true)->whereNull('reconciliation_resolved_at')->count());
        $this->assertSame(1, app(AdminActionCenterService::class)->summary()['payments_reconciliation']);
        $this->assertSame(5, DB::table('product_views')->count());
        $this->assertSame(3, DB::table('search_logs')->count());
        $buyerA = User::where('email', 'c1-buyer-a@example.test')->sole();
        $recommended = app(RecommendationService::class)->forUser($buyerA, 12);
        $this->assertGreaterThan(0, $recommended->count());
        $this->assertTrue($recommended->contains(fn ($product) => str_starts_with($product->reason_label, 'Vì bạn')));
        $this->assertSame(['CONGNGHE20K' => 'SHOP', 'PASSLAI10' => 'PLATFORM'], Voucher::orderBy('code')->pluck('scope', 'code')->all());
        $this->assertSame($inventoryShop->id, Voucher::where('code', 'CONGNGHE20K')->value('shop_id'));
        $this->assertSame(2, ReviewService::published(Review::query())->count());
        $this->assertDatabaseCount('product_videos', 2);
        $this->assertDatabaseCount('video_comments', 4);
        $this->assertDatabaseCount('conversations', 3);
        $this->assertDatabaseCount('messages', 6);
        $this->assertGreaterThan(0, DB::table('notifications')->whereNotNull('read_at')->count());
        $this->assertGreaterThan(0, DB::table('notifications')->whereNull('read_at')->count());
        foreach (Product::with('images')->get() as $product) {
            $this->assertSame(1, $product->images->where('is_primary', true)->count());
            foreach ($product->images as $image) {
                Storage::disk('public')->assertExists($image->image_path);
            }
        }
        foreach (ProductVideo::all() as $video) {
            Storage::disk('public')->assertExists($video->path);
        }
        $this->assertFalse(Carbon::hasTestNow());
        $this->assertFalse(CarbonImmutable::hasTestNow());
        $this->assertLessThan(90, abs(CarbonImmutable::parse($old)->diffInSeconds(CarbonImmutable::now())));
        $snapshot = $this->snapshot();
        $files = Storage::disk('public')->allFiles();
        config(['demo.password' => 'DifferentPrivateTestOnly!']);
        $this->seed(FinalDemoSeeder::class);
        $this->assertSame($snapshot, $this->snapshot());
        $this->assertSame($files, Storage::disk('public')->allFiles());
        $this->assertDatabaseCount('vouchers', 2);
        $this->assertSame(5, DB::table('product_views')->count());
        $this->assertSame(3, DB::table('search_logs')->count());
        $this->assertSame(7, Order::count());
        $this->assertTrue(Hash::check('FictionalTestOnlyC1!', User::where('email', 'c1-admin@example.test')->sole()->password));
    }

    public function test_password_is_required_and_reserved_account_collision_is_not_overwritten(): void
    {
        config(['demo.password' => null]);
        try {
            $this->seed(FinalDemoSeeder::class);
            $this->fail('Expected private password guard');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('DEMO_PASSWORD', $e->getMessage());
        }
        $this->assertDatabaseCount('users', 0);
        config(['demo.password' => 'FictionalTestOnlyC1!']);
        User::factory()->create(['email' => 'c1-admin@example.test', 'name' => 'Unrelated local account']);
        $before = $this->snapshot();
        try {
            $this->seed(FinalDemoSeeder::class);
            $this->fail('Expected reserved identifier conflict');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('conflicts', $e->getMessage());
        }
        $this->assertSame($before, $this->snapshot());
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertFalse(CarbonImmutable::hasTestNow());
    }

    public function test_product_api_exposes_auction_reservation_and_releases_it_when_cancelled(): void
    {
        $product = Product::factory()->active()->create();
        $auction = Auction::factory()->for($product)->create(['seller_id' => $product->shop->owner_id, 'status' => 'LIVE']);
        $this->getJson('/api/v1/products/'.$product->slug)->assertOk()->assertJsonPath('data.auction.id', (string) $auction->id);
        $this->getJson('/api/v1/products')->assertOk()->assertJsonPath('data.0.auction.id', (string) $auction->id);
        $auction->forceFill(['status' => 'CANCELLED'])->save();
        $this->getJson('/api/v1/products/'.$product->slug)->assertOk()->assertJsonPath('data.auction', null);
    }
}
