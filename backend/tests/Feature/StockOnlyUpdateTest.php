<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\AuditLog;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class StockOnlyUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->withHeaders(['Origin' => 'http://localhost:5173', 'Accept' => 'application/json']);
    }

    private function login(User $user): void
    {
        $this->app['session']->flush();
        Auth::forgetGuards();
        $this->actingAs($user, 'web');
    }

    /** A listing that an Admin approved at some point; status and stock are then set directly. */
    private function product(string $status, int $stock, array $data = []): Product
    {
        $product = Product::factory()->active()->create(['status' => $status, 'stock_quantity' => $stock, ...$data]);
        $this->login($product->shop->owner);

        return $product;
    }

    private function restock(Product $product, int $stock, array $extra = []): TestResponse
    {
        return $this->patchJson('/api/v1/seller/products/'.$product->id, ['stock_quantity' => $stock, 'expected_updated_at' => $product->refresh()->updated_at->toISOString(), ...$extra]);
    }

    private function assertStockAudit(Product $product, string $from, string $to, string $reason): void
    {
        $row = AuditLog::where('entity_type', 'products')->where('entity_id', $product->id)->latest('id')->firstOrFail();
        $this->assertSame(['product.stock_adjusted', $from, $to, $reason], [$row->action, $row->from_status, $row->to_status, $row->reason]);
        $this->assertSame($product->shop->owner_id, $row->actor_id);
    }

    public function test_active_listing_stays_active_when_only_stock_changes(): void
    {
        $product = $this->product('ACTIVE', 3);
        $published = $product->published_at->toISOString();
        $this->restock($product, 8)->assertOk()->assertJsonPath('data.status', 'ACTIVE')->assertJsonPath('data.stock_quantity', 8);
        $this->assertStockAudit($product, 'ACTIVE', 'ACTIVE', 'stock: 3 → 8');
        $this->restock($product, 1)->assertOk()->assertJsonPath('data.status', 'ACTIVE');
        $this->assertStockAudit($product, 'ACTIVE', 'ACTIVE', 'stock: 8 → 1');
        $this->assertSame($published, $product->refresh()->published_at->toISOString());
        $this->getJson('/api/v1/products/'.$product->slug)->assertOk()->assertJsonPath('data.stock_quantity', 1);
    }

    public function test_active_listing_becomes_sold_at_zero_stock(): void
    {
        $product = $this->product('ACTIVE', 3);
        $this->restock($product, 0)->assertOk()->assertJsonPath('data.status', 'SOLD');
        $this->assertStockAudit($product, 'ACTIVE', 'SOLD', 'stock: 3 → 0');
        $this->getJson('/api/v1/products/'.$product->slug)->assertNotFound();
    }

    public function test_sold_listing_reopens_and_is_immediately_purchasable(): void
    {
        $product = $this->product('SOLD', 0);
        $this->getJson('/api/v1/products/'.$product->slug)->assertNotFound();
        $this->restock($product, 4)->assertOk()->assertJsonPath('data.status', 'ACTIVE')->assertJsonPath('data.stock_quantity', 4);
        $this->assertStockAudit($product, 'SOLD', 'ACTIVE', 'stock: 0 → 4');

        $this->getJson('/api/v1/products/'.$product->slug)->assertOk()->assertJsonPath('data.id', (string) $product->id);
        $this->assertContains((string) $product->id, collect($this->getJson('/api/v1/products?shop='.$product->shop_id)->assertOk()->json('data'))->pluck('id')->all());
        $buyer = User::factory()->create();
        $address = Address::factory()->for($buyer, 'user')->create();
        $this->login($buyer);
        $this->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 2])->assertCreated();
        $this->getJson('/api/v1/cart')->assertOk()->assertJsonPath('data.items.0.available', true);
        $this->withHeaders(['Idempotency-Key' => (string) Str::uuid()])->postJson('/api/v1/checkout', ['address_id' => $address->id, 'payment_method' => 'COD'])->assertCreated();
        $this->assertSame([2, 'ACTIVE'], [$product->refresh()->stock_quantity, $product->status->value]);
    }

    public function test_sold_listing_with_zero_stock_is_left_untouched(): void
    {
        $product = $this->product('SOLD', 0);
        $before = $product->updated_at->toISOString();
        $this->restock($product, 0)->assertOk()->assertJsonPath('data.status', 'SOLD');
        $this->assertSame($before, $product->refresh()->updated_at->toISOString());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_sold_listing_returns_to_review_when_it_cannot_safely_reopen(): void
    {
        $never = $this->product('SOLD', 0, ['published_at' => null]);
        $this->restock($never, 2)->assertOk()->assertJsonPath('data.status', 'PENDING');
        $this->assertStockAudit($never, 'SOLD', 'PENDING', 'stock: 0 → 2');

        $pendingShop = $this->product('SOLD', 0);
        $pendingShop->shop->update(['status' => 'PENDING']);
        $this->restock($pendingShop, 2)->assertOk()->assertJsonPath('data.status', 'PENDING');

        $inactiveCategory = $this->product('SOLD', 0);
        $inactiveCategory->category->update(['status' => 'INACTIVE']);
        $this->restock($inactiveCategory, 2)->assertOk()->assertJsonPath('data.status', 'PENDING');

        $parent = Category::factory()->create(['status' => 'INACTIVE']);
        $inactiveAncestor = $this->product('SOLD', 0, ['category_id' => Category::factory()->create(['parent_id' => $parent->id])->id]);
        $this->restock($inactiveAncestor, 2)->assertOk()->assertJsonPath('data.status', 'PENDING');

        foreach ([$never, $pendingShop, $inactiveCategory, $inactiveAncestor] as $product) {
            $this->getJson('/api/v1/products/'.$product->slug)->assertNotFound();
        }
    }

    public function test_sold_listing_edited_without_review_cannot_reopen_through_a_restock(): void
    {
        // SOLD listings accept content and image edits without review; restocking must not publish them.
        $content = $this->product('SOLD', 0);
        $this->patchJson('/api/v1/seller/products/'.$content->id, ['name' => 'Tên chưa được duyệt'])->assertOk()->assertJsonPath('data.status', 'SOLD');
        $this->restock($content, 3)->assertOk()->assertJsonPath('data.status', 'PENDING');

        $images = $this->product('SOLD', 0);
        $this->post('/api/v1/seller/products/'.$images->id.'/images', ['images' => [UploadedFile::fake()->image('new.png', 20, 20)]])->assertCreated();
        $this->assertSame('SOLD', $images->refresh()->status->value);
        $this->restock($images, 3)->assertOk()->assertJsonPath('data.status', 'PENDING');

        // Once an Admin approves the edited listing again, a later sell-out can reopen directly.
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $owner = $content->shop->owner;
        $this->login($admin);
        $this->patchJson('/api/v1/admin/products/'.$content->id.'/status', ['expected_status' => 'PENDING', 'status' => 'ACTIVE', 'reason' => 'Đã duyệt lại'])->assertOk();
        $this->login($owner);
        $this->restock($content, 0)->assertOk()->assertJsonPath('data.status', 'SOLD');
        $this->restock($content, 5)->assertOk()->assertJsonPath('data.status', 'ACTIVE');
    }

    public function test_other_statuses_keep_their_status_for_any_stock(): void
    {
        foreach (['DRAFT', 'PENDING', 'REJECTED', 'HIDDEN'] as $status) {
            foreach ([0, 7] as $stock) {
                $product = $this->product($status, 3);
                $this->restock($product, $stock)->assertOk()->assertJsonPath('data.status', $status)->assertJsonPath('data.stock_quantity', $stock);
                $this->assertStockAudit($product, $status, $status, 'stock: 3 → '.$stock);
            }
        }
    }

    public function test_stock_combined_with_content_keeps_the_review_rules(): void
    {
        $active = $this->product('ACTIVE', 3);
        $this->restock($active, 9, ['name' => 'Đổi tên và nhập hàng'])->assertOk()->assertJsonPath('data.status', 'PENDING');
        $row = AuditLog::where('entity_id', $active->id)->latest('id')->firstOrFail();
        $this->assertSame(['product.updated', 'ACTIVE', 'PENDING', null], [$row->action, $row->from_status, $row->to_status, $row->reason]);

        $sold = $this->product('SOLD', 0);
        $this->restock($sold, 9, ['price' => '123000.00'])->assertOk()->assertJsonPath('data.status', 'PENDING');

        $emptied = $this->product('ACTIVE', 3);
        $this->restock($emptied, 0, ['description' => 'Mô tả mới'])->assertOk()->assertJsonPath('data.status', 'SOLD');

        // Sending unchanged content next to a new stock value is still a stock-only change.
        $same = $this->product('ACTIVE', 3);
        $this->restock($same, 6, ['name' => $same->name, 'price' => $same->price])->assertOk()->assertJsonPath('data.status', 'ACTIVE');
        $this->assertStockAudit($same, 'ACTIVE', 'ACTIVE', 'stock: 3 → 6');

        $content = $this->product('ACTIVE', 3);
        $this->patchJson('/api/v1/seller/products/'.$content->id, ['name' => 'Chỉ đổi nội dung'])->assertOk()->assertJsonPath('data.status', 'PENDING');
    }

    public function test_stale_version_missing_version_and_foreign_products_are_rejected(): void
    {
        $product = $this->product('SOLD', 0);
        $url = '/api/v1/seller/products/'.$product->id;
        $stale = $product->updated_at->subSecond()->toISOString();
        $this->patchJson($url, ['stock_quantity' => 5, 'expected_updated_at' => $stale])->assertConflict();
        $this->patchJson($url, ['stock_quantity' => 5])->assertUnprocessable()->assertJsonValidationErrors('expected_updated_at');
        $this->assertSame([0, 'SOLD'], [$product->refresh()->stock_quantity, $product->status->value]);

        $this->login(Shop::factory()->create()->owner);
        $this->restock($product, 5)->assertForbidden();
        $this->login(User::factory()->create());
        $this->restock($product, 5)->assertForbidden();
        $this->assertSame([0, 'SOLD'], [$product->refresh()->stock_quantity, $product->status->value]);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    /** @return array{0: User, 1: Order, 2: Product} */
    private function soldOut(): array
    {
        $buyer = User::factory()->create();
        $address = Address::factory()->for($buyer, 'user')->create();
        $cart = Cart::factory()->for($buyer, 'user')->create();
        $product = Product::factory()->active()->create(['stock_quantity' => 2]);
        CartItem::factory()->for($cart)->for($product)->create(['quantity' => 2]);
        $order = app(CheckoutService::class)->checkout($buyer, $address->id, (string) Str::uuid())['order'];
        $this->assertSame([0, 'SOLD'], [$product->refresh()->stock_quantity, $product->status->value]);

        return [$buyer, $order, $product];
    }

    private function cancel(User $buyer, Order $order): TestResponse
    {
        $this->login($buyer);
        $part = $order->shopOrders()->firstOrFail();

        return $this->postJson('/api/v1/orders/'.$order->id.'/shop-orders/'.$part->id.'/cancel', ['expected_status' => 'PENDING', 'reason' => 'Đổi ý']);
    }

    public function test_cancellation_after_a_restock_adds_stock_and_keeps_the_listing_active(): void
    {
        [$buyer, $order, $product] = $this->soldOut();
        $this->login($product->shop->owner);
        $this->restock($product, 5)->assertOk()->assertJsonPath('data.status', 'ACTIVE');
        $this->cancel($buyer, $order)->assertOk();
        $this->assertSame([7, 'ACTIVE'], [$product->refresh()->stock_quantity, $product->status->value]);
        $this->getJson('/api/v1/products/'.$product->slug)->assertOk()->assertJsonPath('data.stock_quantity', 7);
        $this->assertNotNull($order->shopOrders()->firstOrFail()->stock_restored_at);
    }

    public function test_cancellation_before_any_restock_restores_the_sold_listing(): void
    {
        [$buyer, $order, $product] = $this->soldOut();
        $this->cancel($buyer, $order)->assertOk();
        $this->assertSame([2, 'ACTIVE'], [$product->refresh()->stock_quantity, $product->status->value]);
        // The seller can then keep adjusting stock without a review round trip.
        $this->login($product->shop->owner);
        $this->restock($product, 6)->assertOk()->assertJsonPath('data.status', 'ACTIVE');
    }

    public function test_sold_out_again_after_a_restock_still_restores_safely_on_cancellation(): void
    {
        [, , $product] = $this->soldOut();
        $this->login($product->shop->owner);
        $this->restock($product, 1)->assertOk()->assertJsonPath('data.status', 'ACTIVE');
        $buyer = User::factory()->create();
        $address = Address::factory()->for($buyer, 'user')->create();
        CartItem::factory()->for(Cart::factory()->for($buyer, 'user')->create())->for($product)->create(['quantity' => 1]);
        $order = app(CheckoutService::class)->checkout($buyer, $address->id, (string) Str::uuid())['order'];
        $this->assertSame('SOLD', $product->refresh()->status->value);
        $this->cancel($buyer, $order)->assertOk();
        $this->assertSame([1, 'ACTIVE'], [$product->refresh()->stock_quantity, $product->status->value]);
    }
}
