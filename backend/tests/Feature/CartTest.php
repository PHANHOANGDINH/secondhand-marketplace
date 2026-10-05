<?php

namespace Tests\Feature;

use App\Enums\CategoryStatus;
use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CartTest extends TestCase
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

    private function add(Product $product, int $quantity = 1): TestResponse
    {
        return $this->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => $quantity]);
    }

    public function test_guest_cannot_use_any_cart_endpoint(): void
    {
        $item = CartItem::factory()->create();
        $this->getJson('/api/v1/cart')->assertUnauthorized();
        $this->postJson('/api/v1/cart/items', ['product_id' => $item->product_id, 'quantity' => 1])->assertUnauthorized();
        $this->patchJson('/api/v1/cart/items/'.$item->id, ['quantity' => 2])->assertUnauthorized();
        $this->deleteJson('/api/v1/cart/items/'.$item->id)->assertUnauthorized();
        $this->deleteJson('/api/v1/cart')->assertUnauthorized();
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_cart_is_created_once_and_scoped_to_current_user(): void
    {
        $user = User::factory()->create();
        $other = CartItem::factory()->create();
        $this->loginAs($user);
        $id = $this->getJson('/api/v1/cart')->assertOk()->assertJsonPath('data.items', [])->assertJsonPath('data.subtotal', '0.00')->assertJsonPath('data.total_quantity', 0)->json('data.id');
        $this->getJson('/api/v1/cart')->assertJsonPath('data.id', $id)->assertJsonMissingPath('data.user_id');
        $this->assertSame(1, Cart::where('user_id', $user->id)->count());
        $this->assertNotSame((string) $other->cart_id, $id);
        $this->getJson('/api/v1/cart?user_id='.$other->cart->user_id)->assertUnprocessable();
    }

    public function test_add_then_increment_same_product_without_reserving_stock(): void
    {
        $buyer = User::factory()->create();
        $product = Product::factory()->active()->create(['stock_quantity' => 8, 'price' => '0.10']);
        $this->loginAs($buyer);
        $id = $this->add($product, 2)->assertCreated()->assertJsonPath('data.quantity', 2)->assertJsonPath('data.unit_price', '0.10')->assertJsonPath('data.subtotal', '0.20')->assertJsonPath('data.available', true)->json('data.id');
        $this->add($product, 3)->assertOk()->assertJsonPath('data.id', $id)->assertJsonPath('data.quantity', 5)->assertJsonPath('data.subtotal', '0.50');
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertSame(8, $product->refresh()->stock_quantity);
        $this->getJson('/api/v1/cart')->assertJsonPath('data.item_count', 1)->assertJsonPath('data.total_quantity', 5)->assertJsonPath('data.available_subtotal', '0.50');
    }

    public function test_quantity_validation_and_sensitive_fields_are_rejected(): void
    {
        $this->loginAs(User::factory()->create());
        $product = Product::factory()->active()->create();
        foreach ([0, -1, 100, 1.5, 'many', null] as $quantity) {
            $this->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => $quantity])->assertUnprocessable()->assertJsonValidationErrors('quantity');
        }
        foreach (['cart_id', 'user_id', 'price', 'unit_price', 'subtotal'] as $field) {
            $this->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 1, $field => '1'])->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->postJson('/api/v1/cart/items', ['product_id' => 9999999, 'quantity' => 1])->assertUnprocessable();
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_stock_and_accumulated_quantity_limits_keep_previous_quantity(): void
    {
        $this->loginAs(User::factory()->create());
        $product = Product::factory()->active()->create(['stock_quantity' => 4]);
        $this->add($product, 5)->assertConflict();
        $id = $this->add($product, 3)->assertCreated()->json('data.id');
        $this->add($product, 2)->assertConflict();
        $this->patchJson('/api/v1/cart/items/'.$id, ['quantity' => 5])->assertConflict();
        $this->assertSame(3, CartItem::findOrFail($id)->quantity);
        $product->update(['stock_quantity' => 200]);
        $this->patchJson('/api/v1/cart/items/'.$id, ['quantity' => 99])->assertOk();
        $this->add($product)->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->assertSame(99, CartItem::findOrFail($id)->quantity);
    }

    private function makeUnavailable(Product $product, string $case): void
    {
        match ($case) {
            'draft','pending','rejected','hidden','sold' => $product->update(['status' => strtoupper($case)]),
            'stock-zero' => $product->update(['stock_quantity' => 0]),
            'shop-pending','shop-suspended','shop-rejected' => $product->shop->update(['status' => strtoupper(substr($case, 5))]),
            'category' => $product->category->update(['status' => CategoryStatus::INACTIVE]),
            'ancestor' => $product->category->update(['parent_id' => Category::factory()->create(['status' => CategoryStatus::INACTIVE])->id]),
            'owner-suspended' => $product->shop->owner->forceFill(['status' => UserStatus::SUSPENDED])->save(),
            'product-deleted' => $product->delete(), 'shop-deleted' => $product->shop->delete(), 'owner-deleted' => $product->shop->owner->delete(),
        };
    }

    public function test_add_checks_the_full_public_visibility_rule(): void
    {
        $this->loginAs(User::factory()->create());
        foreach (['draft', 'pending', 'rejected', 'hidden', 'sold', 'stock-zero', 'shop-pending', 'shop-suspended', 'shop-rejected', 'category', 'ancestor', 'owner-suspended', 'product-deleted', 'shop-deleted', 'owner-deleted'] as $case) {
            $product = Product::factory()->active()->create();
            $this->makeUnavailable($product, $case);
            $this->add($product)->assertConflict();
        }
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_owner_including_admin_cannot_add_their_own_shop_product(): void
    {
        $product = Product::factory()->active()->create();
        $owner = $product->shop->owner;
        $this->loginAs($owner);
        $this->add($product)->assertForbidden();
        $owner->forceFill(['role' => UserRole::ADMIN])->save();
        $this->loginAs($owner);
        $this->add($product)->assertForbidden();
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_another_user_and_admin_cannot_mutate_foreign_cart_item(): void
    {
        $item = CartItem::factory()->create();
        $url = '/api/v1/cart/items/'.$item->id;
        foreach ([User::factory()->create(), User::factory()->admin()->create()] as $other) {
            $this->loginAs($other);
            $this->patchJson($url, ['quantity' => 2])->assertForbidden();
            $this->deleteJson($url)->assertForbidden();
        }
        $this->assertSame(1, $item->refresh()->quantity);
    }

    public function test_patch_sets_absolute_quantity_and_rechecks_stock_and_availability(): void
    {
        $item = CartItem::factory()->create(['quantity' => 2]);
        $item->product->update(['stock_quantity' => 5]);
        $this->loginAs($item->cart->user);
        $url = '/api/v1/cart/items/'.$item->id;
        $this->patchJson($url, ['quantity' => 3])->assertOk()->assertJsonPath('data.quantity', 3);
        $this->patchJson($url, ['quantity' => 3])->assertOk()->assertJsonPath('data.quantity', 3);
        $this->patchJson($url, ['quantity' => 0])->assertUnprocessable();
        $this->patchJson($url, ['quantity' => 1, 'product_id' => $item->product_id])->assertUnprocessable();
        $item->product->update(['stock_quantity' => 2]);
        $this->patchJson($url, ['quantity' => 3])->assertConflict();
        $this->getJson('/api/v1/cart')->assertJsonPath('data.items.0.unavailable_reason', 'INSUFFICIENT_STOCK')->assertJsonPath('data.items.0.max_quantity', 2);
        $this->patchJson($url, ['quantity' => 2])->assertOk()->assertJsonPath('data.available', true);
        $item->product->update(['status' => ProductStatus::HIDDEN]);
        $this->patchJson($url, ['quantity' => 1])->assertConflict();
        $this->assertSame(2, $item->refresh()->quantity);
    }

    public function test_remove_and_clear_work_for_unavailable_items_without_touching_other_carts(): void
    {
        $user = User::factory()->create();
        $cart = Cart::factory()->for($user)->create();
        $items = CartItem::factory()->for($cart)->count(2)->create();
        $other = CartItem::factory()->create();
        $this->loginAs($user);
        $items[0]->product->delete();
        $this->deleteJson('/api/v1/cart/items/'.$items[0]->id)->assertNoContent();
        $this->deleteJson('/api/v1/cart')->assertNoContent();
        $this->deleteJson('/api/v1/cart')->assertNoContent();
        $this->getJson('/api/v1/cart')->assertJsonPath('data.id', (string) $cart->id)->assertJsonPath('data.items', [])->assertJsonPath('data.subtotal', '0.00');
        $this->assertDatabaseHas('cart_items', ['id' => $other->id]);
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_current_prices_and_large_decimal_totals_are_not_stored_or_rounded(): void
    {
        $this->loginAs(User::factory()->create());
        $product = Product::factory()->active()->create(['price' => '0.10', 'stock_quantity' => 99]);
        $id = $this->add($product, 3)->assertCreated()->assertJsonPath('data.subtotal', '0.30')->json('data.id');
        $product->update(['price' => '999999.99']);
        $this->getJson('/api/v1/cart')->assertJsonPath('data.items.0.unit_price', '999999.99')->assertJsonPath('data.items.0.subtotal', '2999999.97')->assertJsonPath('data.subtotal', '2999999.97');
        $product->update(['price' => '9999999999999.99']);
        $this->patchJson('/api/v1/cart/items/'.$id, ['quantity' => 99])->assertJsonPath('data.subtotal', '989999999999999.01');
        $this->assertSame(['id', 'cart_id', 'product_id', 'quantity', 'created_at', 'updated_at'], array_keys(CartItem::findOrFail($id)->getAttributes()));
    }

    public function test_existing_unavailable_items_are_retained_with_safe_summaries(): void
    {
        $buyer = User::factory()->create();
        $this->loginAs($buyer);
        $ids = [];
        foreach (['hidden', 'shop-suspended', 'stock-zero', 'category', 'ancestor', 'owner-suspended', 'product-deleted', 'shop-deleted', 'owner-deleted'] as $case) {
            $product = Product::factory()->active()->create(['price' => '1.00', 'moderation_reason' => 'Private reason']);
            $ids[] = $this->add($product)->assertCreated()->json('data.id');
            $this->makeUnavailable($product, $case);
        }
        $response = $this->getJson('/api/v1/cart')->assertOk()->assertJsonCount(9, 'data.items')->assertJsonPath('data.has_unavailable_items', true)->assertJsonPath('data.subtotal', '9.00')->assertJsonPath('data.available_subtotal', '0.00');
        $this->assertSame($ids, $response->json('data.items.*.id'));
        foreach ($response->json('data.items') as $row) {
            $this->assertFalse($row['available']);
            $this->assertSame('PRODUCT_UNAVAILABLE', $row['unavailable_reason']);
            $this->assertSame(0, $row['max_quantity']);
            $this->assertArrayNotHasKey('moderation_reason', $row['product']);
            $this->assertArrayNotHasKey('owner_id', $row['shop']);
            $this->assertNotNull($row['primary_image']);
        }
        $this->assertDatabaseCount('cart_items', 9);
    }

    public function test_multi_shop_totals_and_available_subtotal_are_current(): void
    {
        $this->loginAs(User::factory()->create());
        $a = Product::factory()->active()->create(['price' => '10.25', 'stock_quantity' => 5]);
        $b = Product::factory()->active()->create(['price' => '0.10', 'stock_quantity' => 5]);
        $this->add($a, 2)->assertCreated();
        $this->add($b, 3)->assertCreated();
        $response = $this->getJson('/api/v1/cart')->assertJsonPath('data.subtotal', '20.80')->assertJsonPath('data.available_subtotal', '20.80')->assertJsonPath('data.total_quantity', 5)->assertJsonPath('data.item_count', 2);
        $this->assertSame([(string) $a->shop_id, (string) $b->shop_id], $response->json('data.items.*.shop.id'));
        $b->update(['status' => ProductStatus::HIDDEN]);
        $this->getJson('/api/v1/cart')->assertJsonPath('data.subtotal', '20.80')->assertJsonPath('data.available_subtotal', '20.50');
    }

    public function test_cart_read_eager_loads_without_per_item_queries(): void
    {
        $cart = Cart::factory()->create();
        CartItem::factory()->for($cart)->create();
        $this->loginAs($cart->user);
        DB::enableQueryLog();
        $this->getJson('/api/v1/cart')->assertOk();
        $first = count(DB::getQueryLog());
        DB::disableQueryLog();
        CartItem::factory()->for($cart)->count(5)->create();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/v1/cart')->assertJsonCount(6, 'data.items');
        $many = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThanOrEqual($first + 1, $many);
    }

    public function test_failed_insert_rolls_back_cart_creation_and_does_not_touch_stock(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->active()->create(['stock_quantity' => 3]);
        CartItem::creating(function (): void {
            throw new \RuntimeException('insert failed');
        });
        try {
            app(CartService::class)->add($user, $product->id, 2);
            $this->fail('Expected failure.');
        } catch (\RuntimeException $error) {
            $this->assertSame('insert failed', $error->getMessage());
        } finally {
            CartItem::flushEventListeners();
        }
        $this->assertFalse($user->cart()->exists());
        $this->assertDatabaseCount('cart_items', 0);
        $this->assertSame(3, $product->refresh()->stock_quantity);
    }
}
