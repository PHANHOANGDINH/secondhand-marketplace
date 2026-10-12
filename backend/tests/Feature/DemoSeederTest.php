<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_is_coherent_and_repeatable(): void
    {
        Storage::fake('public');
        config(['demo.password' => 'seed-test-password-1']);
        $this->seed(DatabaseSeeder::class);
        $counts = ['users' => 12, 'addresses' => 9, 'shops' => 3, 'categories' => 13, 'products' => 20,
            'product_images' => 24, 'carts' => 0, 'cart_items' => 0, 'orders' => 0, 'shop_orders' => 0,
            'order_items' => 0, 'payments' => 0, 'reviews' => 0, 'audit_logs' => 0];
        foreach ($counts as $table => $count) {
            $this->assertDatabaseCount($table, $count);
        }
        $this->assertSame(1, User::where('role', UserRole::ADMIN)->count());
        $this->assertTrue(Hash::check('seed-test-password-1', User::where('email', 'buyer@example.com')->value('password')));
        $stocks = Product::orderBy('id')->pluck('stock_quantity', 'id')->all();
        foreach (Product::with('images')->get() as $product) {
            $this->assertSame(1, $product->images->where('is_primary', true)->count());
        }
        config(['demo.password' => 'another-seed-password-2']);
        $this->seed(DatabaseSeeder::class);
        foreach ($counts as $table => $count) {
            $this->assertDatabaseCount($table, $count);
        }
        $this->assertSame($stocks, Product::orderBy('id')->pluck('stock_quantity', 'id')->all());
        // Existing demo accounts are skipped: a rerun never rewrites their passwords.
        $this->assertTrue(Hash::check('seed-test-password-1', User::where('email', 'buyer@example.com')->value('password')));
    }

    public function test_demo_users_require_a_configured_private_password(): void
    {
        foreach ([null, '', 'too-short', 12345678901234] as $password) {
            config(['demo.password' => $password]);
            try {
                $this->seed(DemoUserSeeder::class);
                $this->fail('Seeding must stop without a usable DEMO_PASSWORD.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('DEMO_PASSWORD', $exception->getMessage());
            }
            $this->assertDatabaseCount('users', 0);
        }
    }
}
