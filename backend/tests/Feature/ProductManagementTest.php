<?php

namespace Tests\Feature;

use App\Enums\CategoryStatus;
use App\Enums\ProductStatus;
use App\Enums\ShopStatus;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Shop;
use App\Models\User;
use App\Services\AuditService;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ProductManagementTest extends TestCase
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

    private function data(array $extra = []): array
    {
        return array_replace(['name' => 'Do Cu Phat', 'description' => 'An honestly described used item.', 'category_id' => Category::factory()->create()->id, 'price' => '1250000.00', 'stock_quantity' => 4, 'condition' => 'GOOD'], $extra);
    }

    public function test_guest_no_shop_and_restricted_shop_cannot_write(): void
    {
        $this->postJson('/api/v1/seller/products', $this->data())->assertUnauthorized();
        $this->loginAs(User::factory()->create());
        $this->postJson('/api/v1/seller/products', $this->data())->assertForbidden();
        foreach ([ShopStatus::SUSPENDED, ShopStatus::REJECTED] as $state) {
            $shop = Shop::factory()->create(['status' => $state]);
            $this->loginAs($shop->owner);
            $this->postJson('/api/v1/seller/products', $this->data())->assertForbidden();
            $product = Product::factory()->for($shop)->create();
            $this->getJson('/api/v1/seller/products/'.$product->id)->assertOk();
            $this->patchJson('/api/v1/seller/products/'.$product->id, ['name' => 'Blocked'])->assertForbidden();
        }
    }

    public function test_create_draft_with_stable_global_slug_and_decimal_price(): void
    {
        $shop = Shop::factory()->create(['status' => ShopStatus::PENDING]);
        $this->loginAs($shop->owner);
        $data = $this->data();
        $first = $this->postJson('/api/v1/seller/products', $data)->assertCreated()->assertJsonPath('data.status', 'DRAFT')->assertJsonPath('data.price', '1250000.00')->json('data');
        $this->assertDatabaseHas('products', ['id' => $first['id'], 'shop_id' => $shop->id]);
        $this->patchJson('/api/v1/seller/products/'.$first['id'], ['name' => 'Changed title'])->assertOk()->assertJsonPath('data.slug', 'do-cu-phat');
        $this->deleteJson('/api/v1/seller/products/'.$first['id'])->assertNoContent();
        $this->assertSoftDeleted('products', ['id' => $first['id']]);
        $this->postJson('/api/v1/seller/products', $data)->assertCreated()->assertJsonPath('data.slug', 'do-cu-phat-2');
    }

    public function test_privileged_fields_and_invalid_business_input_are_rejected(): void
    {
        $product = Product::factory()->create();
        $this->loginAs($product->shop->owner);
        foreach (['shop_id' => 999, 'status' => 'ACTIVE', 'slug' => 'hack', 'deleted_at' => '2026-01-01', 'owner_id' => 99, 'image_path' => '../../bad'] as $field => $value) {
            $this->postJson('/api/v1/seller/products', $this->data([$field => $value]))->assertUnprocessable()->assertJsonValidationErrors($field);
            $this->patchJson('/api/v1/seller/products/'.$product->id, [$field => $value])->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->postJson('/api/v1/seller/products', $this->data(['price' => '0', 'stock_quantity' => -1, 'condition' => 'NEW']))->assertUnprocessable()->assertJsonValidationErrors(['price', 'stock_quantity', 'condition']);
        $root = Category::factory()->create(['status' => CategoryStatus::INACTIVE]);
        $child = Category::factory()->create(['parent_id' => $root->id]);
        $this->postJson('/api/v1/seller/products', $this->data(['category_id' => $child->id]))->assertUnprocessable()->assertJsonValidationErrors('category_id');
    }

    public function test_ownership_applies_to_every_seller_action_and_direct_policy(): void
    {
        $product = Product::factory()->create();
        $other = Shop::factory()->create();
        $this->loginAs($other->owner);
        $base = '/api/v1/seller/products/'.$product->id;
        $this->getJson($base)->assertForbidden();
        $this->patchJson($base, ['name' => 'Hijack'])->assertForbidden();
        $this->deleteJson($base)->assertForbidden();
        foreach (['submit', 'hide', 'reopen'] as $action) {
            $this->postJson($base.'/'.$action)->assertForbidden();
        }
        foreach (['view', 'update', 'delete', 'submit', 'hide'] as $ability) {
            $this->assertFalse(Gate::forUser($other->owner)->allows($ability, $product));
        }
        $this->getJson('/api/v1/seller/products')->assertJsonCount(0, 'data');
    }

    public function test_submit_requires_active_shop_images_stock_and_active_category(): void
    {
        $product = Product::factory()->create();
        $owner = $product->shop->owner;
        $this->loginAs($owner);
        $url = '/api/v1/seller/products/'.$product->id.'/submit';
        $product->shop->update(['status' => ShopStatus::PENDING]);
        $this->postJson($url)->assertConflict();
        $product->shop->update(['status' => ShopStatus::ACTIVE]);
        $product->images()->delete();
        $this->postJson($url)->assertUnprocessable()->assertJsonValidationErrors('images');
        ProductImage::factory()->for($product)->create(['is_primary' => true]);
        $product->update(['stock_quantity' => 0]);
        $this->postJson($url)->assertUnprocessable()->assertJsonValidationErrors('stock_quantity');
        $product->update(['stock_quantity' => 2]);
        $product->category->update(['status' => CategoryStatus::INACTIVE]);
        $this->postJson($url)->assertUnprocessable()->assertJsonValidationErrors('category_id');
        $product->category->update(['status' => CategoryStatus::ACTIVE]);
        $this->postJson($url)->assertOk()->assertJsonPath('data.status', 'ACTIVE');
        $this->postJson($url)->assertConflict();
    }

    public function test_publish_without_review_takedown_reopen_and_hide_lifecycle(): void
    {
        $product = Product::factory()->create();
        $owner = $product->shop->owner;
        $admin = User::factory()->admin()->create();
        $url = '/api/v1/seller/products/'.$product->id;
        $moderate = '/api/v1/admin/products/'.$product->id.'/status';
        $this->loginAs($owner);
        // Publishing needs no review.
        $this->postJson($url.'/submit')->assertOk()->assertJsonPath('data.status', 'ACTIVE');
        $published = $product->refresh()->published_at;
        $this->assertNotNull($published);
        $this->loginAs($admin);
        $this->getJson('/api/v1/admin/products?status=PENDING')->assertOk()->assertJsonCount(0, 'data');
        // An admin takes the listing down with a reason the seller sees.
        $this->patchJson($moderate, ['expected_status' => 'ACTIVE', 'status' => 'REJECTED', 'reason' => 'Ảnh không đúng sản phẩm'])->assertOk()->assertJsonPath('data.status', 'REJECTED');
        $this->loginAs($owner);
        // No way around the admin: the old review shortcuts are gone and publishing again is refused.
        $this->postJson($url.'/revise')->assertNotFound();
        $this->postJson($url.'/withdraw')->assertNotFound();
        $this->postJson($url.'/submit')->assertConflict();
        $this->patchJson($url, ['description' => 'Improved complete description'])->assertOk()->assertJsonPath('data.status', 'REJECTED');
        $this->postJson($url.'/reopen')->assertOk()->assertJsonPath('data.status', 'PENDING');
        $this->postJson($url.'/reopen')->assertConflict();
        $this->loginAs($admin);
        $this->getJson('/api/v1/admin/products?status=PENDING')->assertOk()->assertJsonCount(1, 'data');
        $this->patchJson($moderate, ['expected_status' => 'PENDING', 'status' => 'ACTIVE', 'reason' => 'Đã sửa ảnh'])->assertOk()->assertJsonPath('data.status', 'ACTIVE');
        // Reopening keeps the original publication date.
        $this->assertTrue($published->equalTo($product->refresh()->published_at));
        $this->loginAs($owner);
        $this->postJson($url.'/hide')->assertOk()->assertJsonPath('data.status', 'HIDDEN');
        $this->postJson($url.'/submit')->assertOk()->assertJsonPath('data.status', 'ACTIVE');
        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'products', 'entity_id' => $product->id, 'actor_id' => $admin->id, 'from_status' => 'ACTIVE', 'to_status' => 'REJECTED']);
        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'products', 'entity_id' => $product->id, 'actor_id' => $owner->id, 'action' => 'product.reopen', 'from_status' => 'REJECTED', 'to_status' => 'PENDING']);
        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'products', 'entity_id' => $product->id, 'actor_id' => $admin->id, 'from_status' => 'PENDING', 'to_status' => 'ACTIVE']);
    }

    public function test_admin_stale_invalid_self_and_user_moderation_are_blocked(): void
    {
        $product = Product::factory()->create(['status' => ProductStatus::PENDING]);
        $url = '/api/v1/admin/products/'.$product->id.'/status';
        $this->loginAs($product->shop->owner);
        $this->getJson('/api/v1/admin/products')->assertForbidden();
        $this->patchJson($url, ['expected_status' => 'PENDING', 'status' => 'ACTIVE', 'reason' => 'No'])->assertForbidden();
        $admin = User::factory()->admin()->create();
        $this->loginAs($admin);
        $this->patchJson($url, ['expected_status' => 'ACTIVE', 'status' => 'HIDDEN', 'reason' => 'Stale'])->assertConflict();
        $this->patchJson($url, ['expected_status' => 'PENDING', 'status' => 'SOLD', 'reason' => 'Invalid'])->assertConflict();
        $this->patchJson($url, ['expected_status' => 'PENDING', 'status' => 'ACTIVE'])->assertUnprocessable();
        $own = Product::factory()->for(Shop::factory()->for($admin, 'owner'))->create(['status' => ProductStatus::PENDING]);
        $this->assertFalse(Gate::forUser($admin)->allows('moderate', $own));
        $this->patchJson('/api/v1/admin/products/'.$own->id.'/status', ['expected_status' => 'PENDING', 'status' => 'ACTIVE', 'reason' => 'Self'])->assertForbidden();
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_content_and_stock_edits_keep_a_live_listing_live_and_keep_optimistic_rules(): void
    {
        $product = Product::factory()->active()->create();
        $this->loginAs($product->shop->owner);
        $url = '/api/v1/seller/products/'.$product->id;
        $this->patchJson($url, ['name' => 'Updated content'])->assertOk()->assertJsonPath('data.status', 'ACTIVE');
        $product->refresh();
        $this->patchJson($url, ['stock_quantity' => 9])->assertUnprocessable();
        $this->patchJson($url, ['stock_quantity' => 9, 'expected_updated_at' => '2000-01-01T00:00:00Z'])->assertConflict();
        // Stock changes keep the listing on sale too.
        $this->patchJson($url, ['stock_quantity' => 9, 'expected_updated_at' => $product->updated_at->toISOString()])->assertOk()->assertJsonPath('data.status', 'ACTIVE');
        $product->refresh();
        $this->patchJson($url, ['stock_quantity' => 0, 'expected_updated_at' => $product->updated_at->toISOString()])->assertOk()->assertJsonPath('data.status', 'SOLD');
        $product->refresh();
        // Restocking reopens a sold-out listing at once.
        $this->patchJson($url, ['stock_quantity' => 2, 'expected_updated_at' => $product->updated_at->toISOString()])->assertOk()->assertJsonPath('data.status', 'ACTIVE');
    }

    public function test_decimal_representation_never_rounds_float_input(): void
    {
        $shop = Shop::factory()->create();
        $this->loginAs($shop->owner);
        foreach (['0.10', '999999.99', '9999999999999.99', '1250000.00'] as $price) {
            $id = $this->postJson('/api/v1/seller/products', $this->data(['price' => $price]))->assertCreated()->assertJsonPath('data.price', $price)->json('data.id');
            $this->assertSame($price, Product::findOrFail($id)->price);
        }
        foreach (['1.001', '10000000000000.00', '1e3', '-1', 'NaN', 0.1] as $price) {
            $this->postJson('/api/v1/seller/products', $this->data(['price' => $price]))->assertUnprocessable()->assertJsonValidationErrors('price');
        }
    }

    public function test_audit_failure_rolls_back_moderation(): void
    {
        $product = Product::factory()->create(['status' => ProductStatus::PENDING]);
        $admin = User::factory()->admin()->create();
        $this->mock(AuditService::class)->shouldReceive('record')->once()->andThrow(new \RuntimeException('audit unavailable'));
        try {
            app(ProductService::class)->moderate($admin, $product, ['expected_status' => 'PENDING', 'status' => 'ACTIVE', 'reason' => 'Test']);
            $this->fail('Expected failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('audit unavailable', $e->getMessage());
        }
        $this->assertSame(ProductStatus::PENDING, $product->refresh()->status);
        $this->assertNull($product->published_at);
    }

    public function test_soft_delete_preserves_images_and_historical_snapshot(): void
    {
        $product = Product::factory()->create();
        $item = OrderItem::factory()->for($product)->create();
        $image = $product->images()->first();
        $this->loginAs($product->shop->owner);
        $this->deleteJson('/api/v1/seller/products/'.$product->id)->assertNoContent();
        $this->assertSoftDeleted($product);
        $this->assertDatabaseHas('product_images', ['id' => $image->id]);
        $this->assertSame($image->image_path, $item->refresh()->product_image);
    }
}
