<?php

namespace Tests\Feature;

use App\Enums\CategoryStatus;
use App\Enums\ProductCondition;
use App\Enums\ProductStatus;
use App\Enums\ShopStatus;
use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductCatalogTest extends TestCase
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

    private function ids(string $query = ''): array
    {
        return $this->getJson('/api/v1/products'.$query)->assertOk()->json('data.*.id');
    }

    public function test_only_available_products_are_public_and_private_fields_are_redacted(): void
    {
        $visible = Product::factory()->active()->create(['moderation_reason' => 'Private moderation']);
        foreach ([ProductStatus::DRAFT, ProductStatus::PENDING, ProductStatus::REJECTED, ProductStatus::HIDDEN, ProductStatus::SOLD] as $status) {
            $hidden = Product::factory()->create(['status' => $status]);
            $this->getJson('/api/v1/products/'.$hidden->slug)->assertNotFound();
        }
        $this->assertSame([(string) $visible->id], $this->ids());
        $this->getJson('/api/v1/products/'.$visible->slug)->assertOk()->assertJsonMissingPath('data.moderation_reason')->assertJsonMissingPath('data.shop.owner_id')->assertJsonMissingPath('data.deleted_at')->assertJsonMissingPath('data.images.0.image_path');
        $visible->update(['stock_quantity' => 0]);
        $this->assertSame([], $this->ids());
    }

    public function test_public_visibility_rechecks_shop_owner_category_ancestors_and_soft_deletion(): void
    {
        foreach (['shop-pending', 'shop-suspended', 'owner-suspended', 'ancestor-inactive', 'category-inactive', 'product-deleted', 'shop-deleted', 'owner-deleted'] as $case) {
            $product = Product::factory()->active()->create();
            match ($case) {
                'shop-pending' => $product->shop->update(['status' => ShopStatus::PENDING]),'shop-suspended' => $product->shop->update(['status' => ShopStatus::SUSPENDED]),
                'owner-suspended' => $product->shop->owner->forceFill(['status' => UserStatus::SUSPENDED])->save(),
                'ancestor-inactive' => $product->category->update(['parent_id' => Category::factory()->create(['status' => CategoryStatus::INACTIVE])->id]),
                'category-inactive' => $product->category->update(['status' => CategoryStatus::INACTIVE]),
                'product-deleted' => $product->delete(),'shop-deleted' => $product->shop->delete(),'owner-deleted' => $product->shop->owner->delete(),
            };
            $this->getJson('/api/v1/products/'.$product->slug)->assertNotFound();
        }
        $this->assertSame([], $this->ids());
    }

    public function test_search_name_description_and_literal_wildcards(): void
    {
        $a = Product::factory()->active()->create(['name' => 'Phone 0%_!', 'description' => 'First item']);
        $b = Product::factory()->active()->create(['name' => 'Other device', 'description' => 'Phone in description']);
        Product::factory()->active()->create(['name' => 'Unrelated', 'description' => 'Unrelated']);
        $this->assertEqualsCanonicalizing([(string) $a->id, (string) $b->id], $this->ids('?search=Phone'));
        $this->assertSame([(string) $b->id], $this->ids('?search=description'));
        foreach (['0', '%', '_', '!'] as $search) {
            $this->assertSame([(string) $a->id], $this->ids('?search='.rawurlencode($search)));
        }
        $this->assertSame([], $this->ids('?search=missing'));
    }

    public function test_category_descendants_condition_shop_and_price_filters(): void
    {
        $root = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $root->id]);
        $leaf = Category::factory()->create(['parent_id' => $child->id]);
        $a = Product::factory()->active()->create(['category_id' => $leaf->id, 'price' => '0.10', 'condition' => ProductCondition::GOOD]);
        $b = Product::factory()->active()->create(['category_id' => $child->id, 'price' => '999999.99', 'condition' => ProductCondition::FAIR]);
        $c = Product::factory()->active()->create(['price' => '1000000.00']);
        $this->assertEqualsCanonicalizing([(string) $a->id, (string) $b->id], $this->ids('?category='.$root->id));
        $this->assertSame([(string) $a->id], $this->ids('?category='.$leaf->id.'&condition=GOOD'));
        $this->assertSame([(string) $b->id], $this->ids('?min_price=0.11&max_price=999999.99'));
        $this->assertSame([(string) $a->id], $this->ids('?max_price=0.10'));
        $this->assertSame([(string) $c->id], $this->ids('?min_price=1000000.00'));
        $this->assertSame([(string) $b->id], $this->ids('?shop='.$b->shop_id));
    }

    public function test_sorts_and_pagination_return_exact_order(): void
    {
        $a = Product::factory()->active()->create(['price' => '20.00', 'created_at' => now()->subDays(2)]);
        $b = Product::factory()->active()->create(['price' => '10.00', 'created_at' => now()->subDay()]);
        $c = Product::factory()->active()->create(['price' => '20.00', 'created_at' => now()]);
        $this->assertSame([(string) $c->id, (string) $b->id, (string) $a->id], $this->ids('?sort=newest'));
        $this->assertSame([(string) $b->id, (string) $a->id, (string) $c->id], $this->ids('?sort=price_asc'));
        $this->assertSame([(string) $c->id, (string) $a->id, (string) $b->id], $this->ids('?sort=price_desc'));
        $this->getJson('/api/v1/products?sort=newest&per_page=1&page=2')->assertJsonPath('data.0.id', (string) $b->id)->assertJsonPath('meta.total', 3)->assertJsonPath('meta.last_page', 3);
    }

    public function test_invalid_filters_and_hidden_filter_targets_are_rejected(): void
    {
        foreach (['per_page=101', 'page=0', 'sort=price;DROP', 'condition=NEW', 'min_price=-1', 'min_price=3&max_price=2', 'category=99999', 'status=PENDING', 'order_by=name', 'search[]=bad', 'search=a&keyword=b'] as $query) {
            $this->getJson('/api/v1/products?'.$query)->assertUnprocessable();
        }
        $shop = Shop::factory()->create(['status' => ShopStatus::PENDING]);
        $this->getJson('/api/v1/products?shop='.$shop->id)->assertUnprocessable();
        $category = Category::factory()->create(['status' => CategoryStatus::INACTIVE]);
        $this->getJson('/api/v1/products?category='.$category->id)->assertUnprocessable();
    }

    public function test_listing_eager_loads_relations_without_per_row_queries(): void
    {
        Product::factory()->active()->create();
        DB::enableQueryLog();
        $this->getJson('/api/v1/products')->assertOk();
        $first = count(DB::getQueryLog());
        DB::disableQueryLog();
        Product::factory()->active()->count(5)->create();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/v1/products')->assertOk()->assertJsonCount(6, 'data');
        $many = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThanOrEqual($first + 1, $many);
    }
}
