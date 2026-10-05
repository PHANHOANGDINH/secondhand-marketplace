<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\ShopOrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_is_coherent_and_repeatable(): void
    {
        $this->seed(DatabaseSeeder::class);
        $counts = ['users' => 9, 'addresses' => 9, 'shops' => 4, 'categories' => 12, 'products' => 28,
            'product_images' => 56, 'carts' => 3, 'cart_items' => 6, 'orders' => 2, 'shop_orders' => 4,
            'order_items' => 4, 'payments' => 2, 'reviews' => 2, 'audit_logs' => 2];
        foreach ($counts as $table => $count) {
            $this->assertDatabaseCount($table, $count);
        }
        $this->assertSame(1, User::where('role', UserRole::ADMIN)->count());
        $stocks = Product::orderBy('id')->pluck('stock_quantity', 'id')->all();
        foreach (Product::with('images')->get() as $product) {
            $this->assertSame(1, $product->images->where('is_primary', true)->count());
        }
        foreach (Order::with('shopOrders.orderItems.review', 'payments')->get() as $order) {
            $sum = '0.00';
            foreach ($order->shopOrders as $part) {
                $sum = bcadd($sum, $part->total_amount, 2);
                $this->assertNotSame($order->buyer_id, $part->shop->owner_id);
                foreach ($part->orderItems as $item) {
                    $this->assertSame($part->shop_id, $item->product->shop_id);
                    $this->assertSame($part->subtotal, $item->subtotal);
                    if ($item->review) {
                        $this->assertSame(ShopOrderStatus::COMPLETED, $part->status);
                        $this->assertSame($order->buyer_id, $item->review->user_id);
                    }
                }
            }
            $this->assertSame($sum, $order->total_amount);
            $this->assertCount(1, $order->payments);
            $this->assertSame($order->total_amount, $order->payments->first()->amount);
            $this->assertSame($order->order_code === 'DEMO-COMPLETED' ? PaymentStatus::PAID : PaymentStatus::PENDING, $order->payments->first()->status);
        }
        $this->seed(DatabaseSeeder::class);
        foreach ($counts as $table => $count) {
            $this->assertDatabaseCount($table, $count);
        }
        $this->assertSame($stocks, Product::orderBy('id')->pluck('stock_quantity', 'id')->all());
    }
}
