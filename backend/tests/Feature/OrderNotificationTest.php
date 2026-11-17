<?php

namespace Tests\Feature;

use App\Events\NotificationCreated;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class OrderNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeaders(['Origin' => 'http://localhost:5173', 'Accept' => 'application/json']);
    }

    private function signIn(User $user): void
    {
        $this->app['session']->flush();
        Auth::forgetGuards();
        $this->actingAs($user, 'web');
    }

    /** @return array{0: User, 1: Order, 2: list<User>} */
    private function placeCod(int $shops = 1): array
    {
        $buyer = User::factory()->create();
        $address = Address::factory()->for($buyer, 'user')->create();
        $cart = Cart::factory()->for($buyer, 'user')->create();
        $sellers = [];
        for ($i = 0; $i < $shops; $i++) {
            $product = Product::factory()->active()->create(['stock_quantity' => 2, 'price' => '150000.00']);
            CartItem::factory()->for($cart)->for($product)->create(['quantity' => 1]);
            $sellers[] = $product->shop->owner;
        }
        $this->signIn($buyer);
        $id = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/checkout', ['address_id' => $address->id, 'payment_method' => 'COD'])->assertCreated()->json('data.id');

        return [$buyer, Order::with('shopOrders')->findOrFail($id), $sellers];
    }

    private function notices(User $user, string $filter = 'all'): TestResponse
    {
        $this->signIn($user);

        return $this->getJson('/api/v1/notifications?filter='.$filter)->assertOk();
    }

    private function advance(User $seller, int $part, string $from, string $to, array $extra = []): void
    {
        $this->signIn($seller);
        $this->patchJson('/api/v1/seller/orders/'.$part.'/status', ['expected_status' => $from, 'status' => $to, ...$extra])->assertOk();
    }

    public function test_placing_a_cod_order_reminds_the_buyer_and_every_seller_after_commit(): void
    {
        Event::fake([NotificationCreated::class]);
        [$buyer, $order, $sellers] = $this->placeCod(2);
        $code = $order->order_code;
        $this->notices($buyer, 'orders')->assertJsonCount(1, 'data')->assertJsonPath('data.0.type', 'ORDER_PLACED')->assertJsonPath('data.0.message', 'Đặt hàng thành công! Đơn '.$code.' đang chờ người bán xác nhận.')->assertJsonPath('data.0.target_route', '/orders/'.$order->id)->assertJsonPath('data.0.order_id', (string) $order->id)->assertJsonPath('data.0.order_code', $code)->assertJsonPath('unread_count', 1);
        foreach ($sellers as $i => $seller) {
            $part = $order->shopOrders->firstWhere('shop_id', $seller->shop->id);
            $this->notices($seller)->assertJsonCount(1, 'data')->assertJsonPath('data.0.type', 'ORDER_PLACED')->assertJsonPath('data.0.message', 'Bạn có đơn hàng mới '.$code.'. Hãy xác nhận đơn sớm nhé.')->assertJsonPath('data.0.target_route', '/seller/orders/'.$part->id)->assertJsonPath('data.0.shop_order_id', (string) $part->id);
        }
        $this->notices($buyer, 'auction')->assertJsonCount(0, 'data');
        Event::assertDispatchedTimes(NotificationCreated::class, 3);
    }

    public function test_each_seller_status_change_reminds_the_buyer_once(): void
    {
        [$buyer, $order, [$seller]] = $this->placeCod();
        $part = $order->shopOrders->sole();
        $shop = $seller->shop->name;
        $code = $order->order_code;
        $this->advance($seller, $part->id, 'PENDING', 'CONFIRMED');
        $this->advance($seller, $part->id, 'PENDING', 'CONFIRMED'); // idempotent replay: no second reminder
        $this->advance($seller, $part->id, 'CONFIRMED', 'PROCESSING');
        $this->advance($seller, $part->id, 'PROCESSING', 'SHIPPING');
        $this->advance($seller, $part->id, 'SHIPPING', 'COMPLETED', ['cod_collected' => true]);
        $rows = $this->notices($buyer, 'orders')->assertJsonCount(5, 'data')->json('data');
        $byStatus = collect($rows)->where('type', 'ORDER_STATUS_CHANGED')->keyBy('order_status')->map(fn ($row) => $row['message'])->all();
        $this->assertSame([
            'CONFIRMED' => $shop.' đã xác nhận đơn '.$code.'.',
            'PROCESSING' => $shop.' đang chuẩn bị hàng cho đơn '.$code.'.',
            'SHIPPING' => 'Đơn '.$code.' từ '.$shop.' đang được giao đến bạn.',
            'COMPLETED' => 'Đơn '.$code.' từ '.$shop.' đã giao thành công. Hãy đánh giá sản phẩm nhé!',
        ], collect(['CONFIRMED', 'PROCESSING', 'SHIPPING', 'COMPLETED'])->mapWithKeys(fn ($s) => [$s => $byStatus[$s]])->all());
        $this->assertTrue(collect($rows)->every(fn ($row) => $row['target_route'] === '/orders/'.$order->id));
        // The seller hears only about the new order, not about their own actions.
        $this->notices($seller)->assertJsonCount(1, 'data')->assertJsonPath('data.0.type', 'ORDER_PLACED');
    }

    public function test_cancellations_go_to_the_other_party_with_the_reason(): void
    {
        [$buyer, $order, [$seller]] = $this->placeCod();
        $part = $order->shopOrders->sole();
        $this->signIn($buyer);
        $this->postJson('/api/v1/orders/'.$order->id.'/shop-orders/'.$part->id.'/cancel', ['expected_status' => 'PENDING', 'reason' => 'Đặt nhầm màu'])->assertOk();
        $this->notices($seller, 'orders')->assertJsonCount(2, 'data')->assertJsonFragment(['type' => 'ORDER_STATUS_CHANGED', 'order_status' => 'CANCELLED', 'message' => 'Người mua đã hủy đơn '.$order->order_code.'. Lý do: Đặt nhầm màu', 'target_route' => '/seller/orders/'.$part->id]);
        $this->notices($buyer)->assertJsonCount(1, 'data')->assertJsonPath('data.0.type', 'ORDER_PLACED');

        [$buyer2, $order2, [$seller2]] = $this->placeCod();
        $part2 = $order2->shopOrders->sole();
        $this->advance($seller2, $part2->id, 'PENDING', 'CANCELLED', ['reason' => 'Hết hàng', 'failure_category' => 'UNFULFILLABLE']);
        $this->notices($buyer2)->assertJsonFragment(['order_status' => 'CANCELLED', 'message' => $seller2->shop->name.' đã hủy đơn '.$order2->order_code.'. Lý do: UNFULFILLABLE: Hết hàng']);
    }

    public function test_a_rejected_transition_leaves_no_notification(): void
    {
        [$buyer, $order, [$seller]] = $this->placeCod();
        $part = $order->shopOrders->sole();
        $this->signIn($seller);
        $this->patchJson('/api/v1/seller/orders/'.$part->id.'/status', ['expected_status' => 'CONFIRMED', 'status' => 'PROCESSING'])->assertConflict();
        $this->notices($buyer)->assertJsonCount(1, 'data');
        $this->assertDatabaseCount('notifications', 2);
    }
}
