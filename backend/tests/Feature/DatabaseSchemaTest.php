<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Review;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_relationship_graph_and_enum_casts(): void
    {
        $review = Review::factory()->create();
        $item = $review->orderItem;
        $part = $item->shopOrder;
        $order = $part->order;
        $buyer = $order->buyer;
        $address = Address::factory()->for($buyer)->create();
        $cart = Cart::factory()->for($buyer)->create();
        $line = CartItem::factory()->for($cart)->for($item->product)->create();
        $payment = Payment::factory()->for($order)->paid()->create();
        $parent = Category::factory()->create();
        $item->product->category->update(['parent_id' => $parent->id]);

        $this->assertTrue($buyer->addresses->contains($address));
        $this->assertTrue($buyer->cart->is($cart));
        $this->assertTrue($part->shop->owner->shop->is($part->shop));
        $this->assertTrue($part->shop->products->contains($item->product));
        $this->assertTrue($parent->children->first()->parent->is($parent));
        $this->assertCount(1, $item->product->images);
        $this->assertTrue($cart->items->contains($line));
        $this->assertTrue($order->shopOrders->contains($part));
        $this->assertTrue($part->orderItems->contains($item));
        $this->assertTrue($order->payments->contains($payment));
        $this->assertTrue($item->review->is($review));
        $this->assertSame($buyer->id, $review->user_id);
        $this->assertSame($item->product_id, $review->product_id);
        $this->assertSame(UserRole::USER, $buyer->role);
        $this->assertSame(ProductStatus::ACTIVE, $item->product->status);
        $this->assertSame(PaymentStatus::PAID, $payment->refresh()->status);
    }

    public function test_owner_cannot_have_two_shops(): void
    {
        $shop = Shop::factory()->create();
        $this->expectException(QueryException::class);
        $this->expectExceptionCode('23000');
        Shop::factory()->create(['owner_id' => $shop->owner_id]);
    }

    public function test_cart_cannot_repeat_a_product(): void
    {
        $line = CartItem::factory()->create();
        $this->expectException(QueryException::class);
        $this->expectExceptionCode('23000');
        CartItem::factory()->create(['cart_id' => $line->cart_id, 'product_id' => $line->product_id]);
    }

    public function test_order_item_cannot_have_two_reviews(): void
    {
        $review = Review::factory()->create();
        $this->expectException(QueryException::class);
        $this->expectExceptionCode('23000');
        Review::factory()->create(['order_item_id' => $review->order_item_id]);
    }

    public function test_payment_attempts_belong_to_total_order_and_are_unique(): void
    {
        $first = Payment::factory()->failed()->create();
        $second = Payment::factory()->for($first->order)->create(['attempt_number' => 2]);
        $this->assertCount(2, $first->order->payments);
        $this->assertTrue($second->order->is($first->order));
        $this->assertFalse(Schema::hasColumn('payments', 'shop_order_id'));
        $this->expectException(QueryException::class);
        $this->expectExceptionCode('23000');
        Payment::factory()->for($first->order)->create(['attempt_number' => 2]);
    }

    public function test_snapshots_and_history_survive_changes_and_soft_deletes(): void
    {
        $item = OrderItem::factory()->create();
        $snapshot = $item->only(['product_name', 'product_image', 'product_price', 'quantity', 'subtotal']);
        $order = $item->shopOrder->order;
        $shipping = $order->shipping_address_line;
        $address = Address::factory()->for($order->buyer)->create();
        $address->update(['address_line' => 'Changed address']);
        $address->delete();
        $item->product->update(['name' => 'Changed product', 'price' => '900000.00']);
        $item->product->images()->update(['image_path' => 'changed.jpg']);
        $item->product->delete();
        $item->shopOrder->shop->delete();
        $order->buyer->delete();
        $this->assertSame($snapshot, $item->refresh()->only(array_keys($snapshot)));
        $this->assertSame($shipping, $order->refresh()->shipping_address_line);
        $this->assertTrue($item->product->trashed());
        $this->assertTrue($item->shopOrder->shop->trashed());
        $this->assertTrue($order->buyer->trashed());
        $this->assertDatabaseHas('order_items', ['id' => $item->id]);
        $this->expectException(QueryException::class);
        $this->expectExceptionCode('23000');
        $item->product->forceDelete();
    }

    public function test_temporary_children_cascade_on_hard_delete(): void
    {
        $line = CartItem::factory()->create();
        $cart = $line->cart;
        $product = $line->product;
        $cart->delete();
        $this->assertDatabaseMissing('cart_items', ['id' => $line->id]);
        $product->forceDelete();
        $this->assertDatabaseMissing('product_images', ['product_id' => $product->id]);
    }

    public function test_money_roundtrips_exactly_as_decimal(): void
    {
        $order = Order::factory()->create(['subtotal' => '9876543210123.00', 'discount_amount' => '123.00']);
        $payment = Payment::factory()->for($order)->create();
        $this->assertSame('9876543210000.00', $order->refresh()->total_amount);
        $this->assertSame('9876543210000.00', $payment->refresh()->amount);
        $column = DB::selectOne("SELECT DATA_TYPE AS kind, NUMERIC_PRECISION AS precision_value, NUMERIC_SCALE AS scale_value FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'amount'");
        $this->assertSame('decimal', $column->kind);
        $this->assertSame(15, (int) $column->precision_value);
        $this->assertSame(2, (int) $column->scale_value);
    }

    public function test_mysql_checks_reject_invalid_rating(): void
    {
        $review = Review::factory()->create();
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('reviews_rating_check');
        DB::table('reviews')->where('id', $review->id)->update(['rating' => 6]);
    }

    public function test_mysql_checks_reject_unknown_enum(): void
    {
        $user = User::factory()->create();
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('users_role_enum_check');
        DB::table('users')->where('id', $user->id)->update(['role' => 'SUPERUSER']);
    }

    public function test_mysql_transaction_rollback(): void
    {
        $this->assertSame('secondhand_marketplace_test', DB::selectOne('SELECT DATABASE() AS name')->name);
        DB::beginTransaction();
        $user = User::factory()->create();
        DB::rollBack();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertSame(0, (int) DB::selectOne("SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND ENGINE <> 'InnoDB'")->total);
    }

    public function test_user_email_remains_unique_after_soft_delete(): void
    {
        $user = User::factory()->create();
        $user->delete();
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('users_email_unique');
        User::factory()->create(['email' => $user->email]);
    }

    public function test_order_code_is_unique(): void
    {
        $order = Order::factory()->create();
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('orders_order_code_unique');
        Order::factory()->create(['order_code' => $order->order_code]);
    }

    public function test_payment_reference_is_unique_across_orders(): void
    {
        $payment = Payment::factory()->create();
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('payments_transaction_reference_unique');
        Payment::factory()->create(['transaction_reference' => $payment->transaction_reference]);
    }

    public function test_hard_delete_buyer_is_restricted_by_order_history(): void
    {
        $order = Order::factory()->create();
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('orders_buyer_id_foreign');
        $order->buyer->forceDelete();
    }

    public function test_hard_delete_shop_is_restricted_by_order_history(): void
    {
        $part = ShopOrder::factory()->create();
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('shop_orders_shop_id_foreign');
        $part->shop->forceDelete();
    }

    public function test_quantity_and_amount_checks_exist_on_mysql(): void
    {
        $line = CartItem::factory()->create();
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('cart_items_quantity_check');
        $line->update(['quantity' => 0]);
    }

    public function test_order_total_must_match_its_components(): void
    {
        $order = Order::factory()->create();
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('orders_amounts_check');
        $order->update(['total_amount' => '1.00']);
    }

    public function test_query_indexes_and_foreign_keys_are_present(): void
    {
        $productIndexes = collect(Schema::getIndexes('products'))->pluck('columns')->all();
        $this->assertContains(['category_id', 'status', 'deleted_at', 'created_at', 'id'], $productIndexes);
        $this->assertContains(['shop_id', 'status', 'deleted_at', 'created_at', 'id'], $productIndexes);
        $this->assertContains(['status', 'deleted_at', 'price', 'id'], $productIndexes);
        $this->assertContains(['shop_id', 'status', 'completed_at'], collect(Schema::getIndexes('shop_orders'))->pluck('columns')->all());
        $foreign = collect(Schema::getForeignKeys('payments'))->firstWhere('columns', ['order_id']);
        $this->assertSame('orders', $foreign['foreign_table']);
        $this->assertSame('restrict', strtolower($foreign['on_delete']));
        $this->assertSame('restrict', strtolower($foreign['on_update']));
    }
}
