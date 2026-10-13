<?php

namespace Tests\Feature;

use App\Enums\CategoryStatus;
use App\Enums\ProductStatus;
use App\Enums\ShopStatus;
use App\Enums\UserStatus;
use App\Models\Address;
use App\Models\AuditLog;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\AuditService;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeaders(['Origin' => 'http://localhost:5173', 'Accept' => 'application/json']);
    }

    private function fixture(int $shops = 1): array
    {
        $buyer = User::factory()->create();
        $address = Address::factory()->for($buyer, 'user')->create();
        $cart = Cart::factory()->for($buyer, 'user')->create();
        $products = [];
        for ($i = 0; $i < $shops; $i++) {
            $p = Product::factory()->active()->create(['stock_quantity' => 2, 'price' => '0.10']);
            CartItem::factory()->for($cart)->for($p)->create(['quantity' => 2]);
            $products[] = $p;
        }
        $this->actingAs($buyer, 'web');

        return [$buyer, $address, $cart, $products];
    }

    private function checkout(Address $address, ?string $key = null, array $extra = []): TestResponse
    {
        return $this->withHeader('Idempotency-Key', $key ?? (string) Str::uuid())->postJson('/api/v1/checkout', ['address_id' => $address->id, 'payment_method' => 'COD', ...$extra]);
    }

    public function test_guest_cannot_checkout_or_read_orders(): void
    {
        $this->postJson('/api/v1/checkout', [])->assertUnauthorized();
        $this->getJson('/api/v1/orders')->assertUnauthorized();
        $this->getJson('/api/v1/seller/orders')->assertUnauthorized();
    }

    public function test_empty_cart_invalid_address_and_strict_input_validation(): void
    {
        [$buyer,$address,$cart] = $this->fixture();
        $cart->items()->delete();
        $this->checkout($address)->assertUnprocessable()->assertJsonValidationErrors('cart');
        $foreign = Address::factory()->create();
        $this->checkout($foreign)->assertUnprocessable()->assertJsonValidationErrors('address_id');
        $this->postJson('/api/v1/checkout', ['address_id' => 9999999, 'payment_method' => 'COD'])->assertUnprocessable();
        foreach (['price', 'subtotal', 'total', 'shop_id', 'user_id', 'product_name'] as $field) {
            $this->checkout($address, null, [$field => 'bad'])->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->withHeader('Idempotency-Key', 'bad')->postJson('/api/v1/checkout', ['address_id' => $address->id, 'payment_method' => 'COD'])->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
        $this->checkout($address, null, ['payment_method' => 'VNPAY'])->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_multi_shop_checkout_uses_fresh_prices_snapshots_and_exact_decimal_totals(): void
    {
        [$buyer,$address,$cart,$products] = $this->fixture(2);
        $products[1]->update(['price' => '999999.99']);
        $result = $this->checkout($address)->assertCreated()->assertJsonPath('data.total_amount', '2000000.18')->assertJsonPath('data.overall_status', 'PENDING')->assertJsonPath('data.payment_status', 'PENDING')->assertJsonCount(2, 'data.shop_orders')->assertJsonCount(1, 'data.payments')->assertJsonPath('data.payments.0.amount', '2000000.18')->assertJsonPath('data.payments.0.method', 'COD')->assertJsonMissingPath('data.checkout_key')->assertJsonMissingPath('data.request_hash');
        $order = Order::findOrFail($result->json('data.id'));
        $snapshot = $result->json('data');
        foreach ($products as $p) {
            $this->assertSame(0, $p->refresh()->stock_quantity);
            $this->assertSame(ProductStatus::SOLD, $p->status);
            $p->update(['name' => 'Changed', 'price' => '4.50']);
            $p->images()->update(['image_path' => 'changed.webp']);
            $p->delete();
        }
        $address->update(['recipient_name' => 'Changed address']);
        $address->delete();
        $this->getJson('/api/v1/orders/'.$order->id)->assertOk()->assertJsonPath('data.shipping_address', $snapshot['shipping_address'])->assertJsonPath('data.shop_orders', $snapshot['shop_orders']);
        $this->assertSame(0, $cart->items()->count());
        $this->assertDatabaseCount('order_items', 2);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'attempt_number' => 1, 'status' => 'PENDING']);
    }

    public function test_idempotent_replay_precedes_empty_cart_and_deleted_address_checks(): void
    {
        [$buyer,$address,$cart,$products] = $this->fixture();
        $key = (string) Str::uuid();
        $id = $this->checkout($address, $key)->assertCreated()->json('data.id');
        $logs = AuditLog::count();
        $address->delete();
        $this->checkout($address, $key)->assertOk()->assertJsonPath('data.id', $id);
        $this->assertSame($logs, AuditLog::count());
        $this->assertSame(0, $products[0]->refresh()->stock_quantity);
        $other = Address::factory()->for($buyer, 'user')->create();
        $this->checkout($other, $key)->assertConflict();
        $this->checkout($other)->assertUnprocessable();
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_any_unavailable_item_rejects_whole_cart(): void
    {
        [$buyer,$address,$cart,$products] = $this->fixture(2);
        $p = $products[1];
        $changes = [fn () => $p->update(['stock_quantity' => 1]), fn () => $p->update(['stock_quantity' => 2, 'status' => ProductStatus::HIDDEN]), function () use ($p): void {
            $p->update(['status' => ProductStatus::ACTIVE]);
            $p->delete();
        }, fn () => $p->restore()];
        foreach (array_slice($changes, 0, 3) as $change) {
            $change();
            $this->checkout($address)->assertConflict();
            $this->assertDatabaseCount('orders', 0);
            $this->assertSame(2, $cart->items()->count());
            $this->assertSame(2, $products[0]->refresh()->stock_quantity);
        }
        $changes[3]();
        $p->shop->update(['status' => ShopStatus::SUSPENDED]);
        $this->checkout($address)->assertConflict();
        $p->shop->update(['status' => ShopStatus::ACTIVE]);
        $p->shop->owner->forceFill(['status' => UserStatus::SUSPENDED])->save();
        $this->checkout($address)->assertConflict();
        $p->shop->owner->forceFill(['status' => UserStatus::ACTIVE])->save();
        $p->category->update(['status' => CategoryStatus::INACTIVE]);
        $this->checkout($address)->assertConflict();
        $p->category->update(['status' => CategoryStatus::ACTIVE]);
        $p->shop->update(['owner_id' => $buyer->id]);
        $this->checkout($address)->assertForbidden();
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(2, $cart->items()->count());
    }

    public function test_late_audit_failure_rolls_back_all_order_payment_stock_and_cart_changes(): void
    {
        [$buyer,$address,$cart,$products] = $this->fixture();
        $audit = \Mockery::mock(AuditService::class)->makePartial();
        $audit->shouldReceive('record')->andReturnUsing(function ($actor, $entity): void {
            if ($entity === 'payments') {
                throw new \RuntimeException('Injected audit failure');
            }
        });
        $this->app->instance(AuditService::class, $audit);
        try {
            app(CheckoutService::class)->checkout($buyer, $address->id, (string) Str::uuid());
            $this->fail('Expected failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('Injected audit failure', $e->getMessage());
        }
        foreach (['orders', 'shop_orders', 'order_items', 'payments', 'audit_logs'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertSame(2, $products[0]->refresh()->stock_quantity);
        $this->assertSame(ProductStatus::ACTIVE, $products[0]->status);
        $this->assertSame(1, $cart->items()->count());
    }

    public function test_decimal_overflow_is_rejected_before_writes(): void
    {
        [$buyer,$address,$cart,$products] = $this->fixture();
        $products[0]->update(['price' => '9999999999999.99']);
        $this->checkout($address)->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(2, $products[0]->refresh()->stock_quantity);
        $cart->items()->update(['quantity' => 1]);
        $this->checkout($address)->assertCreated()->assertJsonPath('data.total_amount', '9999999999999.99');
    }

    public function test_buyer_and_seller_lists_are_scoped_paginated_and_do_not_expose_other_shops(): void
    {
        [$buyer,$address,$cart,$products] = $this->fixture(2);
        $order = $this->checkout($address)->assertCreated()->json('data');
        $this->getJson('/api/v1/orders?per_page=1')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/orders?per_page=101')->assertUnprocessable();
        $this->getJson('/api/v1/orders/'.$order['id'].'/payments')->assertOk()->assertJsonCount(1, 'data');
        $stranger = User::factory()->create();
        $this->app['session']->flush();
        Auth::forgetGuards();
        $this->actingAs($stranger, 'web');
        $this->getJson('/api/v1/orders/'.$order['id'])->assertForbidden();
        $this->getJson('/api/v1/orders/'.$order['id'].'/payments')->assertForbidden();
        $this->getJson('/api/v1/orders')->assertJsonCount(0, 'data');
        $seller = $products[0]->shop->owner;
        $this->app['session']->flush();
        Auth::forgetGuards();
        $this->actingAs($seller, 'web');
        $part = $order['shop_orders'][0];
        $foreign = $order['shop_orders'][1];
        $this->getJson('/api/v1/seller/orders?status=PENDING&per_page=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 1)->assertJsonMissingPath('data.0.payments')->assertJsonMissingPath('data.0.shop_orders');
        $this->getJson('/api/v1/seller/orders/'.$part['id'])->assertOk()->assertJsonPath('data.shipping_address', $order['shipping_address']);
        $this->getJson('/api/v1/seller/orders/'.$foreign['id'])->assertForbidden();
        $this->patchJson('/api/v1/seller/orders/'.$foreign['id'].'/status', ['expected_status' => 'PENDING', 'status' => 'CONFIRMED'])->assertForbidden();
        $this->getJson('/api/v1/seller/orders?status=bogus')->assertUnprocessable();
    }

    public function test_selected_items_are_ordered_and_the_rest_of_the_cart_is_kept(): void
    {
        [$buyer, $address, $cart, $products] = $this->fixture(3);
        $items = $cart->items()->orderBy('id')->get();
        // An unavailable item that is not selected does not block the purchase of the others.
        $products[2]->update(['status' => ProductStatus::HIDDEN]);
        $key = (string) Str::uuid();
        $response = $this->checkout($address, $key, ['cart_item_ids' => [$items[0]->id]])->assertCreated();
        $response->assertJsonPath('data.total_amount', '0.20')->assertJsonCount(1, 'data.shop_orders')->assertJsonCount(1, 'data.shop_orders.0.items');
        $this->assertSame([$items[1]->id, $items[2]->id], $cart->items()->orderBy('id')->pluck('id')->all());
        $this->assertSame(0, $products[0]->fresh()->stock_quantity);
        $this->assertSame(2, $products[1]->fresh()->stock_quantity);
        $this->assertSame(1, Order::count());

        // Same key, same selection: the same order, nothing else changes.
        $this->checkout($address, $key, ['cart_item_ids' => [$items[0]->id]])->assertOk()->assertJsonPath('data.id', $response->json('data.id'));
        // Same key with another selection, or with the whole cart, is a different request.
        $this->checkout($address, $key, ['cart_item_ids' => [$items[1]->id]])->assertConflict();
        $this->checkout($address, $key)->assertConflict();
        $this->assertSame(1, Order::count());
        $this->assertSame(2, $cart->items()->count());

        // The selection order does not matter for idempotency.
        $pair = (string) Str::uuid();
        $products[2]->update(['status' => ProductStatus::ACTIVE]);
        $first = $this->checkout($address, $pair, ['cart_item_ids' => [$items[2]->id, $items[1]->id]])->assertCreated();
        $this->checkout($address, $pair, ['cart_item_ids' => [$items[1]->id, $items[2]->id]])->assertOk()->assertJsonPath('data.id', $first->json('data.id'));
        $this->assertSame(0, $cart->items()->count());
        $this->assertSame(2, Order::count());
    }

    public function test_selection_must_be_valid_cart_items_of_the_buyer(): void
    {
        [, $address, $cart, $products] = $this->fixture(2);
        $mine = $cart->items()->orderBy('id')->first();
        $foreign = CartItem::factory()->create();
        foreach ([[], 'x', [0], ['a'], [$mine->id, $mine->id]] as $bad) {
            $this->checkout($address, null, ['cart_item_ids' => $bad])->assertUnprocessable();
        }
        $this->checkout($address, null, ['cart_item_ids' => [$foreign->id]])->assertUnprocessable()->assertJsonValidationErrors('cart_item_ids');
        $this->checkout($address, null, ['cart_item_ids' => [$mine->id, 999999]])->assertUnprocessable()->assertJsonValidationErrors('cart_item_ids');
        // A selected item that became unavailable still rejects the request, exactly like a whole-cart checkout.
        $products[0]->update(['stock_quantity' => 1]);
        $this->checkout($address, null, ['cart_item_ids' => [$mine->id]])->assertConflict();
        $this->assertSame(0, Order::count());
        $this->assertSame(2, $cart->items()->count());
        $this->assertDatabaseHas('cart_items', ['id' => $foreign->id]);
    }
}
