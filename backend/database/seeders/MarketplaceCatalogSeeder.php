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
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * A varied demo catalogue: twelve top-level categories, twenty shops spread over the current
 * provinces and about 140 listings in every status, with drawn SVG pictures.
 * Additive and repeatable: existing users, shops, categories and products are never changed.
 */
class MarketplaceCatalogSeeder extends Seeder
{
    private const CONDITIONS = ['LIKE_NEW', 'GOOD', 'GOOD', 'FAIR', 'GOOD', 'LIKE_NEW', 'FAIR', 'POOR'];

    private const CONDITION_LABELS = ['LIKE_NEW' => 'như mới', 'GOOD' => 'tốt', 'FAIR' => 'khá', 'POOR' => 'đã cũ, còn dùng được'];

    // Line drawings on a 24×24 grid; the image scales them up.
    private const GLYPHS = [
        'phone' => 'M8 2.5h8a1.5 1.5 0 0 1 1.5 1.5v16a1.5 1.5 0 0 1-1.5 1.5H8A1.5 1.5 0 0 1 6.5 20V4A1.5 1.5 0 0 1 8 2.5ZM10.5 5h3M11 18.5h2',
        'tablet' => 'M5.5 3h13A1.5 1.5 0 0 1 20 4.5v15a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 19.5v-15A1.5 1.5 0 0 1 5.5 3ZM11 18h2',
        'watch' => 'M8 7.5h8v9H8ZM9 7.5 9.8 3h4.4l.8 4.5M9 16.5l.8 4.5h4.4l.8-4.5M12 10v2.5l1.5 1',
        'laptop' => 'M5 5.5h14v10H5ZM2.5 18.5h19l-1.5-3H4Z',
        'camera' => 'M3.5 8h4L9 5.5h6L16.5 8h4v11h-17ZM12 16.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z',
        'headphones' => 'M4.5 15v-3a7.5 7.5 0 0 1 15 0v3M4.5 14h3v6h-3ZM16.5 14h3v6h-3Z',
        'speaker' => 'M7 3h10v18H7ZM12 18a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM12 8a1.2 1.2 0 1 0 0-2.4A1.2 1.2 0 0 0 12 8Z',
        'shirt' => 'm8.5 4-5 3 2 3.5L8 9v11h8V9l2.5 1.5 2-3.5-5-3a3.5 3.5 0 0 1-7 0Z',
        'pants' => 'M7 3h10l1.5 18h-4L12 10l-2.5 11h-4ZM7 6h10',
        'shoe' => 'M3 9.5V17h18v-2.5c0-1.5-1-2.5-2.5-3L13 10l-2-3.5H8.5V9h-3ZM3 14.5h18',
        'bag' => 'M5 8h14l-1 12H6ZM9 8V6a3 3 0 0 1 6 0v2',
        'chair' => 'M7 3h10v8H7ZM6 11h12v3H6ZM7.5 14v7M16.5 14v7',
        'pot' => 'M4 10h16v6a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4ZM2 10h2M20 10h2M9 10V8.5h6V10M12 8.5V7',
        'vase' => 'M9 3h6M10 3v3c-3 1.5-4.5 4-4.5 7.5S8 21 12 21s6.5-4 6.5-7.5S17 7.5 14 6V3',
        'book' => 'M5 4.5h10.5A2.5 2.5 0 0 1 18 7v12.5H7.5A2.5 2.5 0 0 1 5 17V4.5Zm0 12.5a2.5 2.5 0 0 1 2.5-2.5H18',
        'pencil' => 'M15 4l5 5L9 20H4v-5ZM13 6l5 5',
        'motorbike' => 'M5.5 19a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM18.5 19a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM5.5 16h5l3-5h3l2 5M13.5 11 12 7H9M15 7h2.5',
        'bicycle' => 'M5.5 18a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM18.5 18a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM5.5 14.5 9 8h6l3.5 6.5M9 8l3 6.5H5.5M12 14.5 15 8M8 6h3M14 5.5h2.5',
        'helmet' => 'M4 16a8 8 0 0 1 16 0v1H4ZM4 17h16v2H4ZM12 8v5h8',
        'sofa' => 'M4 11V8a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3M2.5 11h3v4h13v-4h3v7h-19ZM4.5 18v2M19.5 18v2',
        'wardrobe' => 'M5 3h14v17H5ZM12 3v17M10 11v2M14 11v2M6 20v1.5M18 20v1.5',
        'lamp' => 'M8 3h8l2.5 7h-13ZM12 10v9M8 21h8',
        'fridge' => 'M6 2.5h12v19H6ZM6 9h12M9 5v2.5M9 11.5v4',
        'washer' => 'M4 3h16v18H4ZM4 7h16M7 5h1M12 18.5a4.5 4.5 0 1 0 0-9 4.5 4.5 0 0 0 0 9Z',
        'aircon' => 'M2.5 5h19v8h-19ZM5 10.5h14M8 16v3M12 16v4M16 16v3',
        'stroller' => 'M5 4h5v7h10a7 7 0 0 1-15 0V4ZM10 4a8 8 0 0 1 8 7M8 21a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3ZM17 21a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z',
        'blocks' => 'M4 13h7v7H4ZM13 13h7v7h-7ZM8.5 6h7v7h-7Z',
        'onesie' => 'M9 3h6l5 4-2 3-2-1v7l2 5h-5l-1-3-1 3H6l2-5V9l-2 1-2-3Z',
        'dumbbell' => 'M2.5 10v4M5 7.5v9h2.5v-9ZM16.5 7.5v9H19v-9ZM7.5 12h9M21.5 10v4',
        'tent' => 'M12 3.5 2.5 20h19L12 3.5ZM12 11l-3 9M12 11l3 9',
        'racket' => 'M14.5 3a6 6 0 1 1 0 12 6 6 0 0 1 0-12ZM10.3 13.2 3.5 20M11 6.5l7 7M9.5 9l6 6',
        'pethouse' => 'M3 11 12 4l9 7v9H3ZM9.5 20v-4a2.5 2.5 0 0 1 5 0v4',
        'paw' => 'M12 20c-3 0-5-1.5-5-3.5 0-2.5 2.5-4.5 5-4.5s5 2 5 4.5c0 2-2 3.5-5 3.5ZM6 10.5a1.8 1.8 0 1 0 0-3.6 1.8 1.8 0 0 0 0 3.6ZM18 10.5a1.8 1.8 0 1 0 0-3.6 1.8 1.8 0 0 0 0 3.6ZM9.5 7a1.8 1.8 0 1 0 0-3.6 1.8 1.8 0 0 0 0 3.6ZM14.5 7a1.8 1.8 0 1 0 0-3.6 1.8 1.8 0 0 0 0 3.6Z',
        'ball' => 'M12 20a8 8 0 1 0 0-16 8 8 0 0 0 0 16ZM4.5 9.5c3 1 12 1 15 0M4.5 14.5c3-1 12-1 15 0',
        'desk' => 'M2.5 7h19v3h-19ZM4 10v10M20 10v10M13 10h6v5h-6Z',
        'officechair' => 'M8 3h8v9H8ZM6 12h12v2H6ZM12 14v4M7 21l5-3 5 3M5.5 8v4M18.5 8v4',
        'printer' => 'M7 3h10v5H7ZM3.5 8h17v8h-17ZM7 13h10v8H7ZM17.5 10.5h1',
        'guitar' => 'M18.5 3.5 21 6l-2 1-6.5 6.5M12.5 13.5a3 3 0 0 0-2.3-2.9C9 9.5 6.5 10 5 12s-2 5 0 7 5 1.5 7 0c1.5-1 1.5-2.6.5-3.6ZM8.5 15.5a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z',
        'keyboard' => 'M2.5 7h19v10h-19ZM2.5 12h19M5.5 7v5M9 7v5M12.5 7v5M16 7v5M19 7v5',
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || ! in_array(DB::connection()->getDatabaseName(), ['secondhand_marketplace', 'secondhand_marketplace_test'], true)) {
            throw new RuntimeException('Demo seed is only allowed in the project local/test databases.');
        }
        $password = config('demo.password');
        if (! is_string($password) || strlen($password) < 12) {
            throw new RuntimeException('Configure a private DEMO_PASSWORD of at least 12 characters before seeding.');
        }
        $data = json_decode(file_get_contents(resource_path('demo-marketplace/catalog-vn.json')), true, flags: JSON_THROW_ON_ERROR);
        $provinces = config('provinces.list');

        DB::transaction(function () use ($data, $password, $provinces): void {
            $shops = [];
            foreach ($data['shops'] as $row) {
                if (! isset($provinces[$row['province']])) {
                    throw new RuntimeException("Unknown province code {$row['province']} in the demo catalogue.");
                }
                $shops[$row['key']] = $this->shop($row, $password);
            }
            $created = 0;
            $index = 0;
            $rows = [];
            foreach ($data['categories'] as $hue => $group) {
                $parent = $this->parent($group['slug'], $group['name']);
                $sellers = array_values(array_filter($data['shops'], fn (array $row): bool => in_array($group['slug'], $row['groups'], true)));
                $turn = 0;
                foreach ($group['children'] as $childRow) {
                    $child = $this->child($parent, $childRow['slug'], $childRow['name']);
                    foreach ($childRow['items'] as [$name, $price]) {
                        $index++;
                        $rows[] = [$index, $shops[$sellers[$turn++ % count($sellers)]['key']], $child, $name, (string) $price, $childRow['glyph'], $hue * 30];
                    }
                }
            }
            // Listings are created in a fixed interleaved order (53 is coprime with the count) and dated by it,
            // so "newest first" mixes categories and the latest one is minutes old.
            $total = count($rows);
            usort($rows, fn (array $a, array $b): int => ($a[0] * 53) % $total <=> ($b[0] * 53) % $total);
            foreach ($rows as $position => [$index, $shop, $child, $name, $price, $glyph, $hue]) {
                $slug = 'vn-'.Str::slug($name);
                if (Product::withTrashed()->where('slug', $slug)->exists()) {
                    continue;
                }
                $minutesAgo = ($total - $position) * 300 + 5;
                $this->product($index, $shop, $child, $slug, $name, $price, $glyph, $hue, $provinces[$shop->province_code], $minutesAgo);
                $created++;
            }
            $this->command?->info("Marketplace catalogue: added {$created} listings. Existing records preserved.");
        });
    }

    private function shop(array $row, string $password): Shop
    {
        $email = Str::slug($row['owner'], '.').'@passlai.local';
        $owner = User::withTrashed()->where('email', $email)->first();
        if (! $owner) {
            $owner = new User;
            $owner->forceFill(['name' => $row['owner'], 'email' => $email, 'password' => $password, 'role' => UserRole::USER, 'status' => UserStatus::ACTIVE])->save();
        }
        $shop = Shop::withTrashed()->where('owner_id', $owner->id)->first();
        if ($shop) {
            return $shop;
        }
        $slug = 'vn-'.Str::slug($row['name']);
        if (Shop::withTrashed()->where('slug', $slug)->exists()) {
            throw new RuntimeException('Demo shop identifier conflicts with an existing shop; nothing is overwritten.');
        }

        return Shop::create([
            'owner_id' => $owner->id, 'name' => $row['name'], 'slug' => $slug, 'province_code' => $row['province'], 'status' => ShopStatus::ACTIVE,
            'description' => 'Gian hàng đồ đã qua sử dụng. Hàng được kiểm tra trước khi đăng, có thể xem trực tiếp hoặc giao hàng toàn quốc.',
        ]);
    }

    /** Reuses a top-level category with the same slug or name, so earlier seeds are not duplicated. */
    private function parent(string $slug, string $name): Category
    {
        return Category::where('slug', $slug)->first()
            ?? Category::whereNull('parent_id')->where('name', $name)->first()
            ?? Category::create(['slug' => $slug, 'name' => $name, 'status' => CategoryStatus::ACTIVE, 'description' => 'Danh mục đồ cũ: '.$name.'.']);
    }

    private function child(Category $parent, string $slug, string $name): Category
    {
        return Category::where('slug', $slug)->first()
            ?? Category::create(['slug' => $slug, 'name' => $name, 'parent_id' => $parent->id, 'status' => CategoryStatus::ACTIVE]);
    }

    private function product(int $index, Shop $shop, Category $category, string $slug, string $name, string $price, string $glyph, int $hue, string $province, int $minutesAgo): void
    {
        // Mostly live listings, with every other status present for the "Quản lý tin" tabs.
        $status = match (true) {
            $index % 23 === 0 => ProductStatus::SOLD,
            $index % 31 === 0 => ProductStatus::HIDDEN,
            $index % 29 === 0 => ProductStatus::REJECTED,
            $index % 19 === 0 => ProductStatus::PENDING,
            $index % 37 === 0 => ProductStatus::DRAFT,
            default => ProductStatus::ACTIVE,
        };
        $condition = self::CONDITIONS[$index % count(self::CONDITIONS)];
        $published = in_array($status, [ProductStatus::ACTIVE, ProductStatus::SOLD, ProductStatus::HIDDEN], true);
        $product = Product::create([
            'shop_id' => $shop->id, 'category_id' => $category->id, 'name' => $name, 'slug' => $slug,
            'description' => "{$name}, tình trạng ".self::CONDITION_LABELS[$condition].'. Đồ cá nhân dùng kỹ, còn hoạt động tốt, đã vệ sinh sạch sẽ. '
                ."Xem hàng trực tiếp tại {$province} hoặc giao hàng toàn quốc. Nhắn tin để xem thêm ảnh thật.",
            // Every fifth listing is marked down from a price about a fifth higher, rounded to 10,000 ₫.
            'price' => $price, 'compare_at_price' => $index % 5 === 0 ? bcmul(bcdiv(bcmul($price, '1.2', 2), '10000', 0), '10000', 2) : null, 'stock_quantity' => $status === ProductStatus::SOLD ? 0 : 1 + $index % 3, 'condition' => $condition, 'status' => $status,
            'moderation_reason' => $status === ProductStatus::REJECTED ? 'Ảnh chưa đúng sản phẩm thật, vui lòng chụp lại và gửi duyệt.' : null,
            'published_at' => $published ? now()->subMinutes($minutesAgo) : null,
        ]);
        $views = $index % 3 === 0 ? 2 : 1;
        for ($view = 1; $view <= $views; $view++) {
            $path = 'products/'.$product->id.'/vn-'.$view.'.svg';
            Storage::disk('public')->put($path, $this->illustration($glyph, $hue + ($view - 1) * 40, $view));
            $product->images()->create(['image_path' => $path, 'alt_text' => $name, 'is_primary' => $view === 1, 'sort_order' => $view - 1]);
        }
    }

    private function illustration(string $glyph, int $hue, int $view): string
    {
        $path = self::GLYPHS[$glyph] ?? self::GLYPHS['blocks'];
        $hue %= 360;
        $tilt = $view === 1 ? 0 : -8;

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 480 480" width="480" height="480">
<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="hsl({$hue},75%,95%)"/><stop offset="1" stop-color="hsl({$hue},60%,84%)"/></linearGradient></defs>
<rect width="480" height="480" fill="url(#g)"/>
<circle cx="240" cy="228" r="150" fill="hsl({$hue},80%,98%)"/>
<ellipse cx="240" cy="404" rx="120" ry="14" fill="hsl({$hue},40%,30%)" opacity=".1"/>
<g transform="rotate({$tilt} 240 240) translate(96 84) scale(12)" fill="none" stroke="hsl({$hue},45%,24%)" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"><path d="{$path}"/></g>
</svg>
SVG;
    }
}
