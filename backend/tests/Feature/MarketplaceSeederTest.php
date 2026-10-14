<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\MarketplaceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MarketplaceSeederTest extends TestCase
{
    use RefreshDatabase;

    /** Every text a visitor, a seller or an admin can read. */
    private const TEXT = [
        'users' => ['name', 'email'], 'shops' => ['name', 'slug', 'description'], 'categories' => ['name', 'slug', 'description'],
        'products' => ['name', 'slug', 'description', 'moderation_reason'], 'product_images' => ['alt_text', 'image_path'],
        'addresses' => ['recipient_name', 'address_line', 'ward', 'district', 'province'], 'reviews' => ['comment'],
        'messages' => ['body'], 'product_videos' => ['title', 'description'], 'video_comments' => ['body'],
        'reports' => ['description'], 'vouchers' => ['code'], 'orders' => ['order_code', 'shipping_recipient_name', 'shipping_address_line'],
        'shop_orders' => ['cancellation_reason'], 'payments' => ['gateway_transaction_id', 'transaction_reference'],
    ];

    public function test_marketplace_reads_like_a_real_one_and_covers_every_screen(): void
    {
        Storage::fake('public');
        config(['demo.password' => 'seed-test-password-1']);
        $this->seed(MarketplaceSeeder::class);

        foreach (self::TEXT as $table => $columns) {
            foreach ($columns as $column) {
                // Compared in PHP: the MySQL collation ignores accents ("mẫu" would match "màu").
                $hits = DB::table($table)->pluck($column)->filter(fn ($text) => is_string($text) && preg_match('/demo|mẫu|example|minh họa|C1|test/iu', $text))->values()->all();
                $this->assertSame([], $hits, "{$table}.{$column}");
            }
        }
        $this->assertSame(1, User::where('role', 'ADMIN')->count());
        $this->assertSame(29, User::count());
        $this->assertSame(0, User::where('email', 'not like', '%@passlai.local')->count());
        $this->assertTrue(Hash::check('seed-test-password-1', User::where('email', 'admin@passlai.local')->value('password')));

        $orders = DB::table('shop_orders')->pluck('status')->countBy()->all();
        $this->assertSame(['CANCELLED' => 1, 'COMPLETED' => 5, 'CONFIRMED' => 1, 'PENDING' => 1, 'SHIPPING' => 1], collect($orders)->sortKeys()->all());
        $this->assertSame(5, DB::table('reviews')->count());
        $this->assertSame(['CANCELLED' => 0, 'ENDED' => 2, 'LIVE' => 2, 'SCHEDULED' => 1], collect(['CANCELLED' => 0, 'ENDED' => 0, 'LIVE' => 0, 'SCHEDULED' => 0])->merge(Auction::pluck('status')->map->value->countBy())->sortKeys()->all());
        $this->assertSame(1, Auction::where('status', 'ENDED')->whereNotNull('winner_user_id')->count());
        $this->assertSame(3, DB::table('vouchers')->count());
        // Some listings are marked down, always from a higher price.
        $this->assertGreaterThan(10, Product::whereNotNull('compare_at_price')->count());
        $this->assertSame(0, Product::whereColumn('compare_at_price', '<=', 'price')->count());
        $this->assertSame(1, DB::table('payments')->where('reconciliation_required', true)->count());
        $this->assertSame(8, DB::table('wishlists')->count());
        $this->assertSame(3, DB::table('conversations')->count());
        $this->assertSame(1, DB::table('reports')->count());
        $this->assertSame(2, DB::table('product_videos')->count());
        // Orders are spread over the last days instead of all being created now.
        $this->assertTrue(now()->subDays(5)->greaterThan(Order::min('created_at')));

        $counts = collect(array_keys(self::TEXT))->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()])->all();
        $stocks = Product::orderBy('id')->pluck('stock_quantity', 'id')->all();
        $this->seed(MarketplaceSeeder::class);
        $this->assertSame($counts, collect(array_keys(self::TEXT))->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()])->all());
        $this->assertSame($stocks, Product::orderBy('id')->pluck('stock_quantity', 'id')->all());
    }
}
