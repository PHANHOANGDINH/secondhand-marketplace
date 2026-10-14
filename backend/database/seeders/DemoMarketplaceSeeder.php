<?php

namespace Database\Seeders;

use App\Enums\CategoryStatus;
use App\Enums\ProductStatus;
use App\Enums\ShopStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\CategoryService;
use App\Services\ProductQuery;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use RuntimeException;

class DemoMarketplaceSeeder extends Seeder
{
    private const TARGET = 20;

    private const GROUPS = [
        'phones' => ['Điện thoại & máy tính bảng', 'dien-thoai', 'tech'],
        'laptops' => ['Laptop & máy tính', 'laptop', 'tech'],
        'accessories' => ['Phụ kiện công nghệ', 'phu-kien', 'tech'],
        'home' => ['Đồ gia dụng', 'gia-dung', 'home'],
        'fashion' => ['Thời trang', 'thoi-trang', 'style'],
        'books' => ['Sách & học tập', 'sach-hoc-tap', 'style'],
    ];

    private const SHOPS = [
        'tech' => 'Góc Công Nghệ Cũ', 'home' => 'Nhà Nhỏ Tiện Ích', 'style' => 'Tủ Đồ & Góc Sách',
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) && ! filter_var(env('DEMO_MARKETPLACE_ENABLED', false), FILTER_VALIDATE_BOOLEAN)) {
            throw new RuntimeException('Demo seeding outside local/testing requires explicit DEMO_MARKETPLACE_ENABLED=true.');
        }
        $catalog = collect(json_decode(file_get_contents(resource_path('demo-marketplace/catalog.json')), true, flags: JSON_THROW_ON_ERROR));
        DB::transaction(function () use ($catalog): void {
            $before = app(ProductQuery::class)->publicVisible()->count();
            $remaining = max(0, self::TARGET - $before);
            $created = 0;
            $grouped = $catalog->groupBy('group');
            $shops = [];
            $categories = [];

            // Existing records (including moderated/archived/sold items) are never reset.
            foreach ($catalog as $entry) {
                $product = Product::withTrashed()->with('shop.owner', 'images')->where('slug', 'demo-pack-'.$entry['key'])->first();
                if ($product && $product->shop?->slug === 'demo-pack-'.self::GROUPS[$entry['group']][2]
                    && $product->shop->owner?->email === 'demo-pack-'.self::GROUPS[$entry['group']][2].'@example.test') {
                    $this->restoreMissingImages($product, $entry);
                }
            }
            for ($round = 0; $round < 4 && $remaining > 0; $round++) {
                foreach (self::GROUPS as $group => [$name, $reuseSlug, $shopKey]) {
                    $entry = $grouped[$group][$round] ?? null;
                    if (! $entry || $remaining === 0) {
                        continue;
                    }
                    $slug = 'demo-pack-'.$entry['key'];
                    if (Product::withTrashed()->where('slug', $slug)->exists()) {
                        continue;
                    }
                    $category = $categories[$group] ??= $this->category($group, $name, $reuseSlug);
                    if (app(ProductQuery::class)->publicVisible()->where('category_id', $category->id)->count() >= $grouped[$group]->count()) {
                        continue;
                    }
                    $shop = $shops[$shopKey] ??= $this->shop($shopKey);
                    $product = Product::create([
                        'shop_id' => $shop->id, 'category_id' => $category->id, 'name' => $entry['name'], 'slug' => $slug,
                        'description' => $entry['description'], 'price' => $entry['price'], 'stock_quantity' => $entry['stock_quantity'],
                        'condition' => $entry['condition'], 'status' => ProductStatus::ACTIVE, 'published_at' => now(),
                    ]);
                    for ($view = 1; $view <= $entry['images']; $view++) {
                        $path = $this->imagePath($product, $entry['key'], $view);
                        $this->copyMissingImage($entry['key'], $view, $path);
                        $product->images()->create(['image_path' => $path, 'alt_text' => $entry['name'].' — minh họa demo',
                            'is_primary' => $view === 1, 'sort_order' => $view - 1]);
                    }
                    $remaining--;
                    $created++;
                }
            }
            $after = app(ProductQuery::class)->publicVisible()->count();
            $this->command?->info("Public products: {$before} -> {$after}; added {$created}. Existing records preserved.");
        });
    }

    private function category(string $group, string $name, string $reuseSlug): Category
    {
        $reuse = app(CategoryService::class)->publicQuery()->where('slug', $reuseSlug)->first();
        if ($reuse) {
            return $reuse;
        }
        $category = Category::firstOrCreate(['slug' => 'demo-pack-'.$group], [
            'name' => $name, 'description' => 'Danh mục cho bộ sản phẩm demo.', 'status' => CategoryStatus::ACTIVE,
        ]);
        if (! app(CategoryService::class)->publicQuery()->whereKey($category->id)->exists() || $category->name !== $name) {
            throw new RuntimeException('Demo category identifier conflicts with an existing record; nothing is overwritten.');
        }

        return $category;
    }

    private function shop(string $key): Shop
    {
        $email = 'demo-pack-'.$key.'@example.test';
        $owner = User::withTrashed()->firstOrCreate(['email' => $email], [
            'name' => 'Demo Pack · '.self::SHOPS[$key], 'password' => Str::random(48).'a1', 'role' => UserRole::USER, 'status' => UserStatus::ACTIVE,
        ]);
        if ($owner->trashed() || $owner->status !== UserStatus::ACTIVE || $owner->role !== UserRole::USER || $owner->name !== 'Demo Pack · '.self::SHOPS[$key]) {
            throw new RuntimeException('Demo seller identifier conflicts with an existing account; nothing is overwritten.');
        }
        $shop = Shop::withTrashed()->firstOrCreate(['owner_id' => $owner->id], [
            'name' => self::SHOPS[$key], 'slug' => 'demo-pack-'.$key, 'province_code' => ['tech' => '79', 'home' => '01', 'style' => '48'][$key], 'description' => 'Gian hàng minh họa đồ đã qua sử dụng. Dữ liệu dành cho demo marketplace.', 'status' => ShopStatus::ACTIVE,
        ]);
        if ($shop->trashed() || $shop->status !== ShopStatus::ACTIVE || $shop->slug !== 'demo-pack-'.$key) {
            throw new RuntimeException('Demo shop identifier conflicts with an existing shop; nothing is overwritten.');
        }

        return $shop;
    }

    private function imagePath(Product $product, string $key, int $view): string
    {
        return 'products/'.$product->id.'/'.Uuid::uuid5(Uuid::NAMESPACE_URL, 'secondhand-marketplace/demo-pack/'.$key.'/'.$view).'.webp';
    }

    private function copyMissingImage(string $key, int $view, string $path): void
    {
        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            $source = resource_path('demo-marketplace/images/'.$key.'-'.$view.'.webp');
            if (! is_file($source) || ! $disk->put($path, file_get_contents($source))) {
                throw new RuntimeException('Could not copy the repository-owned demo image.');
            }
        }
    }

    private function restoreMissingImages(Product $product, array $entry): void
    {
        for ($view = 1; $view <= $entry['images']; $view++) {
            $path = $this->imagePath($product, $entry['key'], $view);
            if ($product->images->contains('image_path', $path)) {
                $this->copyMissingImage($entry['key'], $view, $path);
            }
        }
    }
}
