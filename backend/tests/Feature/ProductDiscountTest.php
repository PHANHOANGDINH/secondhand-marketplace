<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductDiscountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeaders(['Origin' => 'http://localhost:5173', 'Accept' => 'application/json']);
    }

    private function loginAs(User $user): void
    {
        $this->app['session']->flush();
        Auth::forgetGuards();
        $this->actingAs($user, 'web');
    }

    public function test_seller_sets_and_clears_a_price_before_the_discount_which_must_be_higher_than_the_price(): void
    {
        $shop = Shop::factory()->create(['status' => 'ACTIVE']);
        $this->loginAs($shop->owner);
        $data = ['name' => 'Máy ảnh cũ', 'description' => 'Còn tốt.', 'category_id' => Category::factory()->create()->id, 'price' => '1000000', 'stock_quantity' => 1, 'condition' => 'GOOD'];

        foreach (['1000000', '900000', 'abc', '-1'] as $bad) {
            $this->postJson('/api/v1/seller/products', [...$data, 'compare_at_price' => $bad])->assertUnprocessable()->assertJsonValidationErrors('compare_at_price');
        }
        $id = $this->postJson('/api/v1/seller/products', [...$data, 'compare_at_price' => '1200000'])->assertCreated()
            ->assertJsonPath('data.compare_at_price', '1200000.00')->assertJsonPath('data.discount_percent', 16)->json('data.id');

        // Raising the price to the old price is refused until the seller changes or clears it.
        $this->patchJson("/api/v1/seller/products/{$id}", ['price' => '1200000'])->assertUnprocessable()->assertJsonValidationErrors('compare_at_price');
        $this->assertSame('1000000.00', Product::find($id)->price);
        $this->patchJson("/api/v1/seller/products/{$id}", ['compare_at_price' => '1500000', 'price' => '1200000'])->assertOk()->assertJsonPath('data.discount_percent', 20);
        $this->patchJson("/api/v1/seller/products/{$id}", ['compare_at_price' => null])->assertOk()->assertJsonPath('data.compare_at_price', null)->assertJsonPath('data.discount_percent', null);
    }

    public function test_setting_a_price_before_the_discount_keeps_a_live_listing_live(): void
    {
        $product = Product::factory()->active()->create(['price' => '500000.00']);
        $this->loginAs($product->shop->owner);
        $this->patchJson("/api/v1/seller/products/{$product->id}", ['compare_at_price' => '800000'])->assertOk()->assertJsonPath('data.status', 'ACTIVE')->assertJsonPath('data.discount_percent', 37);
    }

    public function test_cards_show_the_discount_the_auction_times_and_the_shop_voucher_without_its_code_in_a_fixed_number_of_queries(): void
    {
        $discounted = Product::factory()->active()->create(['price' => '750000.00', 'compare_at_price' => '1000000.00']);
        Voucher::factory()->create(['scope' => Voucher::SHOP, 'shop_id' => $discounted->shop_id, 'type' => Voucher::PERCENT, 'value' => '10.00', 'max_discount' => '30000.00', 'code' => 'GIAM10']);
        Voucher::factory()->create(['scope' => Voucher::SHOP, 'shop_id' => $discounted->shop_id, 'type' => Voucher::FIXED, 'value' => '50000.00', 'min_order' => '500000.00', 'code' => 'GIAM50K']);
        $held = Product::factory()->active()->create();
        $auction = Auction::factory()->create(['product_id' => $held->id]);
        // Vouchers that cannot be used now are never advertised.
        foreach ([['status' => Voucher::INACTIVE], ['ends_at' => now()->subMinute()], ['starts_at' => now()->addDay()], ['usage_limit' => 2, 'used_count' => 2]] as $state) {
            Voucher::factory()->create(['scope' => Voucher::SHOP, 'shop_id' => $held->shop_id, ...$state]);
        }

        $rows = collect($this->getJson('/api/v1/products?per_page=50')->assertOk()->json('data'))->keyBy('id');
        $card = $rows[(string) $discounted->id];
        $this->assertSame(['1000000.00', 25], [$card['compare_at_price'], $card['discount_percent']]);
        $this->assertSame(['type' => 'FIXED', 'value' => '50000.00', 'min_order' => '500000.00', 'max_discount' => null], $card['shop']['voucher']);
        $this->assertStringNotContainsString('GIAM', json_encode($card));
        $other = $rows[(string) $held->id];
        $this->assertNull($other['shop']['voucher']);
        $this->assertNull($other['discount_percent']);
        $this->assertSame($auction->ends_at->toISOString(), $other['auction']['ends_at']);
        $this->assertSame($auction->starts_at->toISOString(), $other['auction']['starts_at']);

        // More shops with vouchers do not add queries.
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson('/api/v1/products?per_page=50')->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };
        $before = $count();
        foreach (Product::factory()->active()->count(4)->create() as $product) {
            Voucher::factory()->create(['scope' => Voucher::SHOP, 'shop_id' => $product->shop_id]);
        }
        $this->assertSame($before, $count());
    }
}
