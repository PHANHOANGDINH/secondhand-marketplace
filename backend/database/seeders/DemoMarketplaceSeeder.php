<?php

namespace Database\Seeders;

use App\Enums\ShopStatus;
use App\Models\AuditLog;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Review;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoMarketplaceSeeder extends Seeder
{
    public function run(): void
    {
        $leaves = Category::whereNotNull('parent_id')->orderBy('id')->get();
        foreach (['seller@example.com', 'seller2@example.com', 'seller3@example.com', 'seller4@example.com'] as $index => $email) {
            $owner = User::where('email', $email)->firstOrFail();
            $shop = Shop::withTrashed()->firstOrCreate(['owner_id' => $owner->id], [
                'name' => 'Gian hàng demo '.($index + 1), 'slug' => 'demo-shop-'.($index + 1), 'status' => ShopStatus::ACTIVE,
            ]);
            for ($i = 1; $i <= 7; $i++) {
                $slug = 'demo-product-'.($index + 1).'-'.$i;
                if (Product::withTrashed()->where('slug', $slug)->exists()) {
                    continue;
                }
                $product = Product::factory()->active()->create([
                    'shop_id' => $shop->id, 'category_id' => $leaves[($index * 7 + $i - 1) % $leaves->count()]->id,
                    'slug' => $slug, 'name' => 'Đồ thanh lý '.($index + 1).'-'.$i,
                    'price' => (string) (($index + 1) * $i * 100000), 'stock_quantity' => 5,
                ]);
                ProductImage::factory()->for($product)->create(['sort_order' => 1]);
            }
        }
        $products = Product::where('slug', 'like', 'demo-product-%')->orderBy('id')->get();
        foreach (['buyer@example.com', 'user1@example.com', 'user2@example.com'] as $index => $email) {
            $buyer = User::where('email', $email)->firstOrFail();
            $cart = Cart::firstOrCreate(['user_id' => $buyer->id]);
            foreach ([$products[$index], $products[$index + 7]] as $product) {
                CartItem::firstOrCreate(['cart_id' => $cart->id, 'product_id' => $product->id], ['quantity' => 1]);
            }
        }
        foreach ([['DEMO-COMPLETED', true], ['DEMO-PENDING', false]] as [$code, $completed]) {
            if (Order::where('order_code', $code)->exists()) {
                continue;
            }
            $buyer = User::where('email', 'buyer@example.com')->firstOrFail();
            $selected = collect([$products[$completed ? 0 : 1], $products[$completed ? 7 : 8]]);
            $total = $selected->reduce(fn (string $sum, Product $p): string => bcadd($sum, $p->price, 2), '0.00');
            $address = $buyer->addresses()->firstOrFail();
            $order = Order::factory()->for($buyer, 'buyer')->create([
                'order_code' => $code, 'subtotal' => $total,
                'shipping_recipient_name' => $address->recipient_name, 'shipping_phone' => $address->phone,
                'shipping_address_line' => $address->address_line, 'shipping_ward' => $address->ward,
                'shipping_district' => $address->district, 'shipping_province' => $address->province,
                'shipping_country_code' => $address->country_code,
            ]);
            foreach ($selected as $product) {
                $factory = ShopOrder::factory()->for($order)->for($product->shop);
                if ($completed) {
                    $factory = $factory->completed();
                }
                $part = $factory->create(['subtotal' => $product->price]);
                $item = OrderItem::factory()->for($part)->for($product)->create();
                $product->decrement('stock_quantity');
                if ($completed) {
                    Review::factory()->for($item)->create();
                }
            }
            $payment = Payment::factory()->for($order);
            if ($completed) {
                $payment = $payment->paid();
            }
            $payment->create();
            AuditLog::factory()->create(['actor_id' => $buyer->id, 'entity_id' => $order->id, 'reason' => 'Local demo fixture']);
        }
    }
}
