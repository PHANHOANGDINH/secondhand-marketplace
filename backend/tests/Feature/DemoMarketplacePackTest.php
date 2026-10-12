<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\ProductQuery;
use Database\Seeders\DemoMarketplaceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class DemoMarketplacePackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function unrelatedProducts(int $count): void
    {
        $shop = Shop::factory()->create(['status' => 'ACTIVE']);
        $category = Category::factory()->create(['status' => 'ACTIVE', 'parent_id' => null]);
        Product::factory()->active()->for($shop)->for($category)->count($count)->create();
    }

    private function snapshot(): array
    {
        $result = [];
        foreach (['users', 'shops', 'categories', 'products', 'product_images', 'orders', 'reviews'] as $table) {
            $result[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return $result;
    }

    public function test_empty_catalog_has_exact_requested_mix_valid_local_images_and_no_fake_transactions(): void
    {
        $this->seed(DemoMarketplaceSeeder::class);
        $this->assertSame(20, app(ProductQuery::class)->publicVisible()->count());
        $this->assertDatabaseCount('product_images', 24);
        foreach (['phones' => 4, 'laptops' => 4, 'accessories' => 4, 'home' => 3, 'fashion' => 3, 'books' => 2] as $group => $count) {
            $this->assertSame($count, Product::whereHas('category', fn ($q) => $q->where('slug', 'demo-pack-'.$group))->count());
        }
        foreach (Product::with('images', 'shop.owner', 'category')->get() as $product) {
            $this->assertSame('ACTIVE', $product->shop->status->value);
            $this->assertSame('ACTIVE', $product->shop->owner->status->value);
            $this->assertSame('ACTIVE', $product->category->status->value);
            $this->assertSame(1, $product->images->where('is_primary', true)->count());
            $this->assertContains($product->stock_quantity, [1, 2]);
            foreach ($product->images as $image) {
                $this->assertMatchesRegularExpression('#^products/'.$product->id.'/[a-f0-9-]{36}\.webp$#D', $image->image_path);
                Storage::disk('public')->assertExists($image->image_path);
                $file = Storage::disk('public')->path($image->image_path);
                $size = getimagesize($file);
                $this->assertSame('image/webp', $size['mime']);
                $this->assertSame([960, 720], [$size[0], $size[1]]);
                $this->assertLessThan(150000, filesize($file));
            }
        }
        foreach (['orders', 'shop_orders', 'order_items', 'payments', 'reviews', 'cart_items', 'audit_logs'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->getJson('/api/v1/products?per_page=100')->assertOk()->assertJsonPath('meta.total', 20);
    }

    public function test_rerun_preserves_all_rows_and_existing_user_edits_stock_and_moderation(): void
    {
        $this->seed(DemoMarketplaceSeeder::class);
        $product = Product::firstOrFail();
        $product->update(['name' => 'Edited by seller', 'price' => '123456.00', 'stock_quantity' => 0, 'status' => ProductStatus::HIDDEN]);
        Product::where('id', '!=', $product->id)->firstOrFail()->delete();
        $before = $this->snapshot();
        $files = Storage::disk('public')->allFiles();
        $this->seed(DemoMarketplaceSeeder::class);
        $this->assertSame($before, $this->snapshot());
        $this->assertSame($files, Storage::disk('public')->allFiles());
        $this->assertSame(18, app(ProductQuery::class)->publicVisible()->count());
    }

    public function test_fills_only_public_deficit_and_preserves_unrelated_records(): void
    {
        $this->unrelatedProducts(5);
        $old = Product::with('images')->orderBy('id')->get()->toJson();
        $ids = Product::pluck('id');
        $this->seed(DemoMarketplaceSeeder::class);
        $this->assertSame(20, app(ProductQuery::class)->publicVisible()->count());
        $this->assertSame(15, Product::where('slug', 'like', 'demo-pack-%')->count());
        $this->assertSame($old, Product::with('images')->whereIn('id', $ids)->orderBy('id')->get()->toJson());
        $before = $this->snapshot();
        $this->seed(DemoMarketplaceSeeder::class);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_existing_twenty_public_products_result_in_no_new_records_or_images(): void
    {
        $this->unrelatedProducts(20);
        $before = $this->snapshot();
        $this->seed(DemoMarketplaceSeeder::class);
        $this->assertSame($before, $this->snapshot());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_reserved_email_collision_is_not_adopted_or_overwritten_and_rolls_back(): void
    {
        $user = User::factory()->create(['email' => 'demo-pack-tech@example.test', 'name' => 'Real account']);
        $before = $this->snapshot();
        try {
            $this->seed(DemoMarketplaceSeeder::class);
            $this->fail('Expected protected demo identifier collision.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('conflicts', $exception->getMessage());
        }
        $this->assertSame($before, $this->snapshot());
        $this->assertSame('Real account', $user->fresh()->name);
    }

    public function test_reuses_visible_existing_categories_without_changing_them(): void
    {
        $category = Category::factory()->create(['slug' => 'dien-thoai', 'name' => 'Existing phones', 'status' => 'ACTIVE', 'parent_id' => null]);
        $before = $category->fresh()->getAttributes();
        $this->seed(DemoMarketplaceSeeder::class);
        $this->assertSame($before, $category->fresh()->getAttributes());
        $this->assertSame(4, Product::where('category_id', $category->id)->count());
    }

    public function test_rerun_repairs_missing_owned_asset_without_resetting_database(): void
    {
        $this->seed(DemoMarketplaceSeeder::class);
        $image = Product::firstOrFail()->images()->firstOrFail();
        $before = $this->snapshot();
        $hash = hash_file('sha256', Storage::disk('public')->path($image->image_path));
        Storage::disk('public')->delete($image->image_path);
        $this->seed(DemoMarketplaceSeeder::class);
        $this->assertSame($hash, hash_file('sha256', Storage::disk('public')->path($image->image_path)));
        $this->assertSame($before, $this->snapshot());
    }

    public function test_production_requires_explicit_operator_opt_in(): void
    {
        $this->app['env'] = 'production';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('explicit');
        $this->app->make(DemoMarketplaceSeeder::class)->run();
    }
}
