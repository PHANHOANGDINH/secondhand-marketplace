<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\Bid;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SellerInventoryTest extends TestCase
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

    private function shop(): Shop
    {
        $shop = Shop::factory()->create();
        $this->login($shop->owner);

        return $shop;
    }

    private function product(Shop $shop, string $status, int $stock, array $data = []): Product
    {
        return Product::factory()->for($shop)->create(['status' => $status, 'stock_quantity' => $stock, ...$data]);
    }

    public function test_guests_and_accounts_without_shop_are_denied(): void
    {
        foreach (['inventory', 'action-center'] as $path) {
            $this->getJson('/api/v1/seller/'.$path)->assertUnauthorized();
        }
        $this->login(User::factory()->create());
        foreach (['inventory', 'action-center'] as $path) {
            $this->getJson('/api/v1/seller/'.$path)->assertNotFound();
        }
    }

    public function test_counts_are_scoped_and_exclude_hidden_and_deleted_stock_alerts(): void
    {
        $shop = $this->shop();
        foreach ([['ACTIVE', 5], ['ACTIVE', 6], ['SOLD', 0], ['PENDING', 0], ['REJECTED', 2], ['HIDDEN', 0], ['DRAFT', 1]] as [$status,$stock]) {
            $this->product($shop, $status, $stock);
        }
        $this->product($shop, 'ACTIVE', 1)->delete();
        $this->product(Shop::factory()->create(), 'ACTIVE', 1);
        $this->getJson('/api/v1/seller/action-center')->assertOk()->assertJsonPath('data.low_stock_products', 1)->assertJsonPath('data.out_of_stock_products', 1)->assertJsonPath('data.pending_products', 1)->assertJsonPath('data.rejected_products', 1)->assertJsonPath('data.low_stock_threshold', 5)->assertJsonStructure(['data' => ['server_time']]);
    }

    public function test_configured_threshold_is_used_in_api_and_badges(): void
    {
        config(['seller-inventory.low_stock_threshold' => 2]);
        $shop = $this->shop();
        $this->product($shop, 'ACTIVE', 3);
        $this->getJson('/api/v1/seller/inventory')->assertOk()->assertJsonPath('low_stock_threshold', 2)->assertJsonPath('data.0.inventory_state', 'NORMAL');
        $this->getJson('/api/v1/seller/action-center')->assertJsonPath('data.low_stock_products', 0);
    }

    public function test_filters_are_paginated_owned_and_soft_delete_safe(): void
    {
        $shop = $this->shop();
        foreach ([['ACTIVE', 3], ['ACTIVE', 9], ['SOLD', 0], ['PENDING', 3], ['HIDDEN', 3], ['REJECTED', 3]] as [$status,$stock]) {
            $this->product($shop, $status, $stock);
        }
        $this->product($shop, 'ACTIVE', 1)->delete();
        $this->product(Shop::factory()->create(), 'ACTIVE', 1);
        foreach (['all' => 6, 'low_stock' => 1, 'out_of_stock' => 1, 'active' => 2, 'pending' => 1, 'hidden' => 1, 'rejected' => 1] as $filter => $total) {
            $response = $this->getJson('/api/v1/seller/inventory?filter='.$filter.'&per_page=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', $total);
            $this->assertSame((string) $shop->id, $response->json('data.0.shop.id'));
        }
    }

    public function test_sorting_and_literal_name_search(): void
    {
        $shop = $this->shop();
        $a = $this->product($shop, 'ACTIVE', 1, ['name' => 'Hàng % đặc biệt', 'created_at' => now()->subDays(3), 'updated_at' => now()->subDays(2)]);
        $b = $this->product($shop, 'ACTIVE', 9, ['name' => 'Hàng thường']);
        foreach (['stock_asc' => $a, 'stock_desc' => $b, 'newest' => $b, 'updated' => $b] as $sort => $product) {
            $this->getJson('/api/v1/seller/inventory?sort='.$sort)->assertJsonPath('data.0.id', (string) $product->id);
        }
        $this->getJson('/api/v1/seller/inventory?search=%25')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', (string) $a->id);
    }

    public function test_invalid_filters_and_client_shop_scope_are_rejected(): void
    {
        $this->shop();
        foreach (['filter=bad', 'sort=bad', 'page=0', 'per_page=101', 'shop_id=1'] as $query) {
            $this->getJson('/api/v1/seller/inventory?'.$query)->assertUnprocessable();
        }
        $this->getJson('/api/v1/seller/action-center?shop_id=1')->assertUnprocessable();
    }

    public function test_orders_requiring_action_match_fulfillment_payment_guards(): void
    {
        $shop = $this->shop();
        foreach (['PENDING', 'CONFIRMED', 'PROCESSING', 'SHIPPING', 'COMPLETED', 'CANCELLED'] as $status) {
            ShopOrder::factory()->for($shop)->create(['status' => $status]);
        }
        $unpaid = Order::factory()->create(['payment_method' => 'ZALOPAY']);
        ShopOrder::factory()->for($shop)->for($unpaid)->create();
        $paid = Order::factory()->create(['payment_method' => 'ZALOPAY']);
        Payment::factory()->for($paid)->create(['method' => 'ZALOPAY', 'status' => 'PAID']);
        ShopOrder::factory()->for($shop)->for($paid)->create();
        $blocked = Order::factory()->create(['payment_method' => 'ZALOPAY']);
        Payment::factory()->for($blocked)->create(['method' => 'ZALOPAY', 'status' => 'PAID', 'reconciliation_required' => true]);
        ShopOrder::factory()->for($shop)->for($blocked)->create();
        ShopOrder::factory()->create();
        $this->getJson('/api/v1/seller/action-center')->assertJsonPath('data.orders_requiring_action', 5);
        $this->getJson('/api/v1/seller/orders?action_required=1')->assertOk()->assertJsonPath('meta.total', 5);
    }

    public function test_live_and_ending_soon_use_server_time_and_owned_products(): void
    {
        $this->freezeTime();
        $shop = $this->shop();
        foreach ([30, 90, -1] as $minutes) {
            Auction::factory()->for($this->product($shop, 'ACTIVE', 1))->create(['starts_at' => now()->subHour(), 'ends_at' => now()->addMinutes($minutes)]);
        }
        Auction::factory()->for($this->product($shop, 'ACTIVE', 1))->create(['status' => 'SCHEDULED', 'starts_at' => now()->addMinute()]);
        Auction::factory()->create();
        $this->getJson('/api/v1/seller/action-center')->assertJsonPath('data.live_auctions', 2)->assertJsonPath('data.auctions_ending_soon', 1);
        $this->getJson('/api/v1/seller/auctions?live_now=1')->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson('/api/v1/seller/auctions?ending_soon=1')->assertOk()->assertJsonPath('meta.total', 1);
        config(['seller-inventory.ending_soon_minutes' => 100]);
        $this->getJson('/api/v1/seller/action-center')->assertJsonPath('data.auctions_ending_soon', 2);
        $this->getJson('/api/v1/auctions?ending_soon=1')->assertUnprocessable();
        $deleted = $this->product($shop, 'ACTIVE', 1);
        Auction::factory()->for($deleted)->create(['ends_at' => now()->addMinutes(30)]);
        $deleted->delete();
        $this->getJson('/api/v1/seller/action-center')->assertJsonPath('data.live_auctions', 2);
        $this->getJson('/api/v1/seller/auctions?live_now=1')->assertJsonPath('meta.total', 2);
        $this->getJson('/api/v1/seller/auctions?ending_soon=1')->assertJsonPath('meta.total', 2);
    }

    public function test_inactive_shop_can_read_but_cannot_update_stock_or_fulfill(): void
    {
        $shop = $this->shop();
        $shop->update(['status' => 'SUSPENDED']);
        $product = $this->product($shop, 'ACTIVE', 2);
        ShopOrder::factory()->for($shop)->create();
        $this->getJson('/api/v1/seller/inventory')->assertOk()->assertJsonPath('data.0.stock_editable', false);
        $this->getJson('/api/v1/seller/action-center')->assertJsonPath('data.low_stock_products', 0)->assertJsonPath('data.orders_requiring_action', 0);
        $this->patchJson('/api/v1/seller/products/'.$product->id, ['stock_quantity' => 1, 'expected_updated_at' => $product->updated_at->toISOString()])->assertForbidden();
    }

    public function test_quick_stock_reuses_existing_moderation_and_conflict_guards(): void
    {
        $shop = $this->shop();
        $product = $this->product($shop, 'ACTIVE', 3);
        $version = $product->updated_at->toISOString();
        $this->patchJson('/api/v1/seller/products/'.$product->id, ['stock_quantity' => 0, 'expected_updated_at' => $version])->assertOk()->assertJsonPath('data.status', 'SOLD');
        $this->patchJson('/api/v1/seller/products/'.$product->id, ['stock_quantity' => 2, 'expected_updated_at' => $version])->assertConflict();
        $this->patchJson('/api/v1/seller/products/'.$product->id, ['stock_quantity' => 2, 'expected_updated_at' => $product->refresh()->updated_at->toISOString()])->assertOk()->assertJsonPath('data.status', 'PENDING');
    }

    public function test_stock_validation_and_idor(): void
    {
        $shop = $this->shop();
        $product = $this->product($shop, 'DRAFT', 2);
        foreach ([-1, 'abc', 1.5, 4294967296] as $value) {
            $this->patchJson('/api/v1/seller/products/'.$product->id, ['stock_quantity' => $value, 'expected_updated_at' => $product->updated_at->toISOString()])->assertUnprocessable()->assertJsonValidationErrors('stock_quantity');
        }
        $this->patchJson('/api/v1/seller/products/'.$product->id, ['stock_quantity' => 1])->assertUnprocessable()->assertJsonValidationErrors('expected_updated_at');
        $other = Product::factory()->create();
        $this->patchJson('/api/v1/seller/products/'.$other->id, ['stock_quantity' => 1, 'expected_updated_at' => $other->updated_at->toISOString()])->assertForbidden();
        $this->assertSame(2, $product->refresh()->stock_quantity);
    }

    public function test_stock_is_locked_for_scheduled_live_and_winner_checkout_claims(): void
    {
        $shop = $this->shop();
        foreach (['SCHEDULED', 'LIVE', 'ENDED'] as $status) {
            $product = $this->product($shop, 'ACTIVE', 2);
            $auction = Auction::factory()->for($product)->create(['status' => $status === 'ENDED' ? 'LIVE' : $status]);
            if ($status === 'ENDED') {
                $bid = Bid::factory()->for($auction)->create();
                $auction->forceFill(['status' => 'ENDED', 'winner_user_id' => $bid->user_id, 'winning_bid_id' => $bid->id, 'winner_checkout_expires_at' => now()->addHour()])->save();
            }
            $this->getJson('/api/v1/seller/inventory?search='.urlencode($product->name))->assertOk();
            $this->patchJson('/api/v1/seller/products/'.$product->id, ['stock_quantity' => 0, 'expected_updated_at' => $product->updated_at->toISOString()])->assertConflict()->assertJsonPath('message', 'Sản phẩm đang được giữ cho đấu giá.');
            $this->assertSame(2, $product->refresh()->stock_quantity);
        }
    }

    public function test_expired_or_cancelled_claim_does_not_block_stock(): void
    {
        $shop = $this->shop();
        foreach (['ENDED', 'CANCELLED'] as $status) {
            $product = $this->product($shop, 'ACTIVE', 2);
            Auction::factory()->for($product)->create(['status' => $status, 'winner_checkout_expires_at' => now()->subMinute()]);
            $this->patchJson('/api/v1/seller/products/'.$product->id, ['stock_quantity' => 0, 'expected_updated_at' => $product->updated_at->toISOString()])->assertOk();
        }
    }

    public function test_deleted_products_are_not_mutable(): void
    {
        $shop = $this->shop();
        $product = $this->product($shop, 'ACTIVE', 2);
        $product->delete();
        $this->patchJson('/api/v1/seller/products/'.$product->id, ['stock_quantity' => 0, 'expected_updated_at' => $product->updated_at->toISOString()])->assertNotFound();
    }

    public function test_inventory_queries_do_not_grow_per_product_and_summary_is_aggregate(): void
    {
        $shop = $this->shop();
        $this->product($shop, 'ACTIVE', 1);
        DB::enableQueryLog();
        $this->getJson('/api/v1/seller/inventory')->assertOk();
        $single = count(DB::getQueryLog());
        DB::disableQueryLog();
        Product::factory()->active()->for($shop)->count(19)->create();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson('/api/v1/seller/inventory')->assertOk()->assertJsonCount(20, 'data');
        $many = count(DB::getQueryLog());
        $this->assertLessThanOrEqual($single + 2, $many);
        DB::flushQueryLog();
        $this->getJson('/api/v1/seller/action-center')->assertOk();
        $this->assertLessThanOrEqual(8, count(DB::getQueryLog()));
        DB::disableQueryLog();
    }
}
