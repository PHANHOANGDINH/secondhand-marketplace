<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\MarketplaceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class MarketplaceCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogue_is_varied_spread_over_current_provinces_and_repeatable(): void
    {
        Storage::fake('public');
        config(['demo.password' => 'seed-test-password-1']);
        $this->seed(DatabaseSeeder::class);
        $this->seed(MarketplaceCatalogSeeder::class);

        $products = Product::where('slug', 'like', 'vn-%')->with('images', 'category.parent', 'shop')->get();
        $this->assertCount(137, $products);
        $this->assertCount(20, Shop::where('slug', 'like', 'vn-%')->get());
        // Twelve top-level groups, the existing "Sách & học tập" reused instead of duplicated.
        $this->assertCount(12, $products->map(fn (Product $product) => $product->category->parent_id)->unique());
        $this->assertSame(1, Category::whereNull('parent_id')->where('name', 'Sách & học tập')->count());
        foreach (['ACTIVE', 'DRAFT', 'PENDING', 'HIDDEN', 'REJECTED', 'SOLD'] as $status) {
            $this->assertTrue($products->contains('status.value', $status), $status);
        }
        $this->assertGreaterThan(100, $products->where('status.value', 'ACTIVE')->count());
        foreach ($products as $product) {
            $this->assertSame(1, $product->images->where('is_primary', true)->count());
            foreach ($product->images as $image) {
                Storage::disk('public')->assertExists($image->image_path);
                $this->assertStringStartsWith('<svg', Storage::disk('public')->get($image->image_path));
            }
            $this->assertSame($product->status->value === 'ACTIVE' || $product->status->value === 'SOLD' || $product->status->value === 'HIDDEN', $product->published_at !== null);
        }
        $provinces = Shop::whereNotNull('province_code')->pluck('province_code')->unique();
        $this->assertGreaterThanOrEqual(18, $provinces->count());
        $this->assertEmpty($provinces->diff(array_map('strval', array_keys(config('provinces.list')))));
        $this->assertSame(['demo-pack-home' => '01', 'demo-pack-style' => '48', 'demo-pack-tech' => '79'], Shop::where('slug', 'like', 'demo-pack-%')->pluck('province_code', 'slug')->sortKeys()->all());
        $this->assertTrue(Hash::check('seed-test-password-1', User::where('email', 'nguyen.minh.anh@passlai.local')->value('password')));

        // The province filter finds the listings of one province only.
        $hue = $this->getJson('/api/v1/products?province=46&per_page=50')->assertOk();
        $this->assertNotEmpty($hue->json('data'));
        $this->assertSame(['Huế'], array_values(array_unique($hue->json('data.*.shop.province_name'))));

        $counts = ['users' => User::count(), 'shops' => Shop::count(), 'categories' => Category::count(), 'products' => Product::count()];
        config(['demo.password' => 'another-seed-password-2']);
        $this->seed(MarketplaceCatalogSeeder::class);
        $this->assertSame($counts, ['users' => User::count(), 'shops' => Shop::count(), 'categories' => Category::count(), 'products' => Product::count()]);
        $this->assertTrue(Hash::check('seed-test-password-1', User::where('email', 'nguyen.minh.anh@passlai.local')->value('password')));
    }

    public function test_a_private_password_is_required(): void
    {
        config(['demo.password' => 'short']);
        $this->expectException(RuntimeException::class);
        $this->seed(MarketplaceCatalogSeeder::class);
    }
}
