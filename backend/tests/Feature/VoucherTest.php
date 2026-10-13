<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Auction;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
use App\Services\AuctionService;
use App\Services\VoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class VoucherTest extends TestCase
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

    /** A signed-in buyer with one cart line per price, each from a different shop. */
    private function cart(array $prices): array
    {
        $buyer = User::factory()->create();
        $address = Address::factory()->for($buyer, 'user')->create();
        $cart = Cart::factory()->for($buyer, 'user')->create();
        $products = [];
        foreach ($prices as $price) {
            $product = Product::factory()->active()->create(['stock_quantity' => 3, 'price' => $price]);
            CartItem::factory()->for($cart)->for($product)->create(['quantity' => 1]);
            $products[] = $product;
        }
        $this->login($buyer);

        return [$buyer, $address, $cart, $products];
    }

    private function checkout(Address $address, array $extra = [], ?string $key = null, string $method = 'COD'): TestResponse
    {
        return $this->withHeader('Idempotency-Key', $key ?? (string) Str::uuid())->postJson('/api/v1/checkout', ['address_id' => $address->id, 'payment_method' => $method, ...$extra]);
    }

    private function preview(array $body = []): TestResponse
    {
        return $this->postJson('/api/v1/checkout/preview', $body);
    }

    public function test_shop_and_platform_vouchers_price_split_and_snapshot_an_order(): void
    {
        [$buyer, $address, , $products] = $this->cart(['100000.00', '200000.00']);
        $shopVoucher = Voucher::factory()->forShop($products[0]->shop)->percent('10.00', '5000.00')->create(['code' => 'SHOPTEN']);
        $platform = Voucher::factory()->create(['code' => 'SAN10K', 'value' => '10000.00']);
        $body = ['platform_voucher' => 'san10k', 'shop_vouchers' => [$products[0]->shop_id => ' shopten ']];

        // Preview first: same numbers, nothing written.
        $before = [Order::count(), VoucherRedemption::count(), $platform->fresh()->used_count];
        $this->preview($body)->assertOk()
            ->assertJsonPath('data.shop_orders.0.discount_shop', '5000.00')->assertJsonPath('data.shop_orders.0.discount_platform', '3220.00')->assertJsonPath('data.shop_orders.0.total', '91780.00')
            ->assertJsonPath('data.shop_orders.1.discount_shop', '0.00')->assertJsonPath('data.shop_orders.1.discount_platform', '6780.00')->assertJsonPath('data.shop_orders.1.total', '193220.00')
            ->assertJsonPath('data.subtotal', '300000.00')->assertJsonPath('data.discount_total', '15000.00')->assertJsonPath('data.total', '285000.00')
            ->assertJsonPath('data.platform_voucher_code', 'SAN10K')->assertJsonPath('data.errors.platform_voucher', null)->assertJsonCount(0, 'data.errors.shop_vouchers');
        $this->assertSame($before, [Order::count(), VoucherRedemption::count(), $platform->fresh()->used_count]);
        $this->assertSame(2, CartItem::count());

        // 10% of 100,000 is capped at 5,000. Platform 10,000 over bases 95,000 and 200,000:
        // floor(3220.33) = 3,220 and floor(6779.66) = 6,779; the spare 1 đ goes to the last shop order.
        $response = $this->checkout($address, $body)->assertCreated()
            ->assertJsonPath('data.subtotal', '300000.00')->assertJsonPath('data.discount_amount', '15000.00')->assertJsonPath('data.total_amount', '285000.00')
            ->assertJsonPath('data.platform_voucher_code', 'SAN10K')->assertJsonPath('data.payable_amount', '285000.00')->assertJsonPath('data.payments.0.amount', '285000.00');
        $order = Order::with('shopOrders')->sole();
        [$first, $second] = [$order->shopOrders->firstWhere('shop_id', $products[0]->shop_id), $order->shopOrders->firstWhere('shop_id', $products[1]->shop_id)];
        $this->assertTrue($second->id > $first->id);
        $this->assertSame(['100000.00', '5000.00', '3220.00', '8220.00', '91780.00', 'SHOPTEN'], [$first->subtotal, $first->discount_shop, $first->discount_platform, $first->discount_amount, $first->total_amount, $first->voucher_code]);
        $this->assertSame(['200000.00', '0.00', '6780.00', '6780.00', '193220.00', null], [$second->subtotal, $second->discount_shop, $second->discount_platform, $second->discount_amount, $second->total_amount, $second->voucher_code]);
        $response->assertJsonPath('data.shop_orders.0.discount_shop', '5000.00')->assertJsonPath('data.shop_orders.0.voucher_code', 'SHOPTEN');
        $this->assertSame([1, 1], [$shopVoucher->fresh()->used_count, $platform->fresh()->used_count]);
        $this->assertSame([[$shopVoucher->id, $first->id, '5000.00'], [$platform->id, null, '10000.00']], VoucherRedemption::orderBy('id')->get()->map(fn ($row) => [$row->voucher_id, $row->shop_order_id, $row->discount_amount])->all());
        $this->assertSame($buyer->id, VoucherRedemption::first()->user_id);

        // Rule 11: editing or switching off the vouchers later does not touch the stored order.
        $platform->update(['value' => '1.00', 'status' => 'INACTIVE']);
        $shopVoucher->update(['status' => 'INACTIVE']);
        $this->getJson('/api/v1/orders/'.$order->id)->assertOk()->assertJsonPath('data.total_amount', '285000.00')->assertJsonPath('data.platform_voucher_code', 'SAN10K')->assertJsonPath('data.shop_orders.0.voucher_code', 'SHOPTEN');
    }

    public function test_discounts_round_down_to_whole_dong_and_never_exceed_the_base(): void
    {
        $percent = Voucher::factory()->percent('15.00')->make();
        $this->assertSame('4999.00', VoucherService::discount($percent, '33333.00'));
        $this->assertSame('0.00', VoucherService::discount($percent, '6.00'));
        $this->assertSame('1200.00', VoucherService::discount(Voucher::factory()->percent('50.00', '1200.50')->make(), '100000.00'));
        $this->assertSame('30000.00', VoucherService::discount(Voucher::factory()->make(['value' => '50000.00']), '30000.00'));
        $this->assertSame('1999.00', VoucherService::discount(Voucher::factory()->make(['value' => '1999.99']), '30000.00'));
        $this->assertSame('100000.00', VoucherService::discount(Voucher::factory()->percent('100.00')->make(), '100000.00'));

        // Proportional split: every share is floored and the shares always add up to the discount.
        $this->assertSame([7 => '3333.00', 8 => '3333.00', 9 => '3334.00'], VoucherService::allocate('10000.00', [7 => '100000.00', 8 => '100000.00', 9 => '100000.00']));
        $this->assertSame([1 => '0.00', 2 => '1.00'], VoucherService::allocate('1.00', [1 => '999.00', 2 => '1.00']));
        // The last shop order cannot take more than its base: the rest moves to the one before it.
        $this->assertSame([1 => '5.00', 2 => '0.00'], VoucherService::allocate('5.00', [1 => '5.00', 2 => '0.00']));
        $this->assertSame([1 => '2.00', 2 => '1.00', 3 => '0.00'], VoucherService::allocate('3.00', [1 => '2.50', 2 => '1.90', 3 => '0.60']));
        $this->assertSame([1 => '0.00', 2 => '0.00'], VoucherService::allocate('0.00', [1 => '10.00', 2 => '20.00']));
        foreach ([['777.00', [1 => '13.00', 2 => '4444.00', 3 => '90001.00']], ['99999.00', [5 => '100000.00', 6 => '1.00', 7 => '33333.00']]] as [$discount, $bases]) {
            $shares = VoucherService::allocate($discount, $bases);
            $this->assertSame($discount, array_reduce($shares, fn ($sum, $share) => bcadd($sum, $share, 2), '0.00'));
            foreach ($shares as $shopId => $share) {
                $this->assertTrue(bccomp($share, $bases[$shopId], 2) <= 0);
            }
        }

        // A shop order may not be discounted to nothing: the payment must stay positive.
        [, $address, , $products] = $this->cart(['30000.00']);
        Voucher::factory()->forShop($products[0]->shop)->create(['code' => 'ALLOFIT', 'value' => '50000.00']);
        $this->checkout($address, ['shop_vouchers' => [$products[0]->shop_id => 'ALLOFIT']])->assertUnprocessable()->assertJsonValidationErrors('shop_vouchers.'.$products[0]->shop_id);
        Voucher::factory()->forShop($products[0]->shop)->create(['code' => 'ALMOST', 'value' => '29999.00']);
        $this->checkout($address, ['shop_vouchers' => [$products[0]->shop_id => 'ALMOST']])->assertCreated()->assertJsonPath('data.total_amount', '1.00');
    }

    public function test_minimum_order_is_checked_against_each_vouchers_own_base(): void
    {
        [, $address, , $products] = $this->cart(['100000.00', '50000.00']);
        $shop = $products[0]->shop_id;
        Voucher::factory()->forShop($products[0]->shop)->create(['code' => 'SHOPMIN', 'value' => '20000.00', 'min_order' => '100000.00']);
        Voucher::factory()->forShop($products[1]->shop)->create(['code' => 'TOOHIGH', 'value' => '1000.00', 'min_order' => '50000.01']);
        // Platform base is what is left after the shop voucher: 80,000 + 50,000 = 130,000.
        Voucher::factory()->create(['code' => 'SAN130', 'value' => '1000.00', 'min_order' => '130000.00']);
        Voucher::factory()->create(['code' => 'SAN131', 'value' => '1000.00', 'min_order' => '130001.00']);

        $this->preview(['platform_voucher' => 'SAN131', 'shop_vouchers' => [$shop => 'SHOPMIN', $products[1]->shop_id => 'TOOHIGH']])->assertOk()
            ->assertJsonPath('data.errors.platform_voucher', 'Đơn chưa đạt giá trị tối thiểu 130.001 ₫ để dùng mã này.')
            ->assertJsonPath('data.errors.shop_vouchers.'.$products[1]->shop_id, 'Đơn chưa đạt giá trị tối thiểu 50.000 ₫ để dùng mã này.')
            ->assertJsonPath('data.shop_orders.0.discount_shop', '20000.00')->assertJsonPath('data.total', '130000.00');
        $this->checkout($address, ['platform_voucher' => 'SAN131', 'shop_vouchers' => [$shop => 'SHOPMIN']])->assertUnprocessable()->assertJsonValidationErrors('platform_voucher');
        $this->assertSame(0, Order::count());
        $this->checkout($address, ['platform_voucher' => 'SAN130', 'shop_vouchers' => [$shop => 'SHOPMIN']])->assertCreated()->assertJsonPath('data.total_amount', '129000.00');
    }

    public function test_unusable_vouchers_are_rejected_with_a_vietnamese_reason_and_nothing_is_written(): void
    {
        [$buyer, $address, , $products] = $this->cart(['100000.00', '100000.00']);
        [$shopA, $shopB] = [$products[0]->shop, $products[1]->shop];
        $cases = [
            'NOTFOUND' => [null, 'Mã giảm giá không tồn tại.'],
            'EXPIRED' => [['ends_at' => now()->subMinute(), 'starts_at' => now()->subDays(2)], 'Mã giảm giá đã hết hạn.'],
            'FUTURE' => [['starts_at' => now()->addHour()], 'Mã giảm giá chưa đến thời gian áp dụng.'],
            'SWITCHEDOFF' => [['status' => 'INACTIVE'], 'Mã giảm giá đã ngừng áp dụng.'],
            'USEDUP' => [['usage_limit' => 2, 'used_count' => 2], 'Mã giảm giá đã hết lượt sử dụng.'],
            'MINEONCE' => [['per_user_limit' => 1], 'Bạn đã dùng hết số lượt cho mã này.'],
        ];
        foreach ($cases as $code => [$state]) {
            if ($state !== null) {
                Voucher::factory()->create(['code' => $code, ...$state]);
            }
        }
        $previous = Order::factory()->create(['buyer_id' => $buyer->id]);
        VoucherRedemption::create(['voucher_id' => Voucher::where('code', 'MINEONCE')->value('id'), 'user_id' => $buyer->id, 'order_id' => $previous->id, 'discount_amount' => '1.00']);
        foreach ($cases as $code => [, $message]) {
            $this->preview(['platform_voucher' => $code])->assertOk()->assertJsonPath('data.errors.platform_voucher', $message)->assertJsonPath('data.discount_total', '0.00');
            $this->checkout($address, ['platform_voucher' => $code])->assertUnprocessable()->assertJsonPath('errors.platform_voucher.0', $message);
        }
        // A released use no longer counts against the per-user limit; another buyer's use never did.
        VoucherRedemption::query()->update(['released_at' => now()]);
        $this->preview(['platform_voucher' => 'MINEONCE'])->assertJsonPath('data.errors.platform_voucher', null);

        // Scope and ownership.
        Voucher::factory()->forShop($shopA)->create(['code' => 'OFSHOPA']);
        Voucher::factory()->create(['code' => 'OFPLATFORM']);
        $this->preview(['platform_voucher' => 'OFSHOPA', 'shop_vouchers' => [$shopA->id => 'OFPLATFORM', $shopB->id => 'OFSHOPA', 999999 => 'OFSHOPA']])->assertOk()
            ->assertJsonPath('data.errors.platform_voucher', 'Đây là mã của gian hàng, hãy nhập ở ô mã của gian hàng đó.')
            ->assertJsonPath('data.errors.shop_vouchers.'.$shopA->id, 'Đây là mã toàn sàn, hãy nhập ở ô mã của sàn.')
            ->assertJsonPath('data.errors.shop_vouchers.'.$shopB->id, 'Mã này không thuộc gian hàng này.')
            ->assertJsonPath('data.errors.shop_vouchers.999999', 'Đơn không có sản phẩm của gian hàng này.');
        $this->checkout($address, ['shop_vouchers' => [$shopB->id => 'OFSHOPA']])->assertUnprocessable()->assertJsonValidationErrors('shop_vouchers.'.$shopB->id);
        // One platform code per order and one code per shop are enforced by the request shape itself.
        $this->checkout($address, ['platform_voucher' => ['OFPLATFORM', 'SAN2']])->assertUnprocessable();
        $this->checkout($address, ['shop_vouchers' => [$shopA->id => ['OFSHOPA', 'X']]])->assertUnprocessable();
        $this->checkout($address, ['shop_vouchers' => ['abc' => 'OFSHOPA']])->assertUnprocessable();

        // Own shop and inactive shop.
        $shopB->update(['status' => 'SUSPENDED']);
        Voucher::factory()->forShop($shopB)->create(['code' => 'OFSHOPB']);
        $this->preview(['shop_vouchers' => [$shopB->id => 'OFSHOPB']])->assertJsonPath('data.errors.shop_vouchers.'.$shopB->id, 'Gian hàng hiện không hoạt động.');
        $shopB->update(['status' => 'ACTIVE']);
        $this->login($shopA->owner);
        $cart = Cart::factory()->for($shopA->owner, 'user')->create();
        CartItem::factory()->for($cart)->for($products[0])->create();
        $this->preview(['shop_vouchers' => [$shopA->id => 'OFSHOPA']])->assertJsonPath('data.errors.shop_vouchers.'.$shopA->id, 'Không thể dùng mã của gian hàng của chính bạn.');

        $this->assertSame(1, Order::count());
        $this->assertSame(1, VoucherRedemption::count());
        $this->assertSame(0, Voucher::where('code', 'OFSHOPA')->value('used_count'));
    }

    public function test_auction_checkout_rejects_vouchers(): void
    {
        // A real winner inside the checkout window, so only the voucher fields can be the reason for the rejection.
        $auction = Auction::factory()->create();
        $buyer = User::factory()->create();
        $address = Address::factory()->for($buyer, 'user')->create();
        app(AuctionService::class)->placeBid($buyer, $auction, '110.00');
        $auction->forceFill(['ends_at' => now()->subSecond()])->save();
        $auction = app(AuctionService::class)->finalize($auction);
        Voucher::factory()->create(['code' => 'SAN10K']);
        $this->login($buyer);
        foreach ([['platform_voucher' => 'SAN10K'], ['shop_vouchers' => [$auction->product->shop_id => 'SAN10K']]] as $extra) {
            $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/auctions/'.$auction->id.'/checkout', ['address_id' => $address->id, 'payment_method' => 'COD', ...$extra])
                ->assertUnprocessable()->assertJsonValidationErrors(array_key_first($extra));
        }
        $this->assertSame(0, Voucher::sole()->used_count);
        $this->assertSame(0, Order::count());
        // Without voucher fields the same request is accepted.
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/auctions/'.$auction->id.'/checkout', ['address_id' => $address->id, 'payment_method' => 'COD'])->assertCreated()->assertJsonPath('data.discount_amount', '0.00');
    }

    public function test_idempotency_covers_the_voucher_codes(): void
    {
        [, $address, , $products] = $this->cart(['100000.00']);
        $voucher = Voucher::factory()->create(['code' => 'SAN10K', 'value' => '10000.00', 'usage_limit' => 5]);
        Voucher::factory()->create(['code' => 'SAN20K', 'value' => '20000.00']);
        Voucher::factory()->forShop($products[0]->shop)->create(['code' => 'SHOP5K', 'value' => '5000.00']);
        $key = (string) Str::uuid();
        $order = $this->checkout($address, ['platform_voucher' => 'SAN10K'], $key)->assertCreated()->json('data.id');
        // Same key, same code (any letter case): the stored order, no second use.
        $this->checkout($address, ['platform_voucher' => 'san10k'], $key)->assertOk()->assertJsonPath('data.id', $order)->assertJsonPath('data.total_amount', '90000.00');
        $this->assertSame(1, $voucher->fresh()->used_count);
        $this->assertSame(1, VoucherRedemption::count());
        // Same key with another code, an extra code, or no code is a different request.
        $this->checkout($address, ['platform_voucher' => 'SAN20K'], $key)->assertConflict();
        $this->checkout($address, ['platform_voucher' => 'SAN10K', 'shop_vouchers' => [$products[0]->shop_id => 'SHOP5K']], $key)->assertConflict();
        $this->checkout($address, [], $key)->assertConflict();
        $this->assertSame(1, Order::count());
        $this->assertSame(1, $voucher->fresh()->used_count);
    }

    public function test_buy_now_with_vouchers_prices_only_the_selected_lines(): void
    {
        [, $address, $cart, $products] = $this->cart(['100000.00', '200000.00']);
        $items = $cart->items()->orderBy('id')->get();
        Voucher::factory()->forShop($products[0]->shop)->create(['code' => 'SHOPA5K', 'value' => '5000.00']);
        Voucher::factory()->forShop($products[1]->shop)->create(['code' => 'SHOPB5K', 'value' => '5000.00']);
        Voucher::factory()->percent('10.00')->create(['code' => 'SAN10PC']);
        $body = ['cart_item_ids' => [$items[1]->id], 'platform_voucher' => 'SAN10PC', 'shop_vouchers' => [$products[1]->shop_id => 'SHOPB5K']];
        $this->preview($body)->assertOk()->assertJsonCount(1, 'data.shop_orders')->assertJsonPath('data.shop_orders.0.discount_shop', '5000.00')->assertJsonPath('data.shop_orders.0.discount_platform', '19500.00')->assertJsonPath('data.total', '175500.00');
        // A code for a shop that is not part of the selection is an error, not silently ignored.
        $this->checkout($address, [...$body, 'shop_vouchers' => [$products[0]->shop_id => 'SHOPA5K']])->assertUnprocessable()->assertJsonValidationErrors('shop_vouchers.'.$products[0]->shop_id);
        $this->checkout($address, $body)->assertCreated()->assertJsonPath('data.total_amount', '175500.00')->assertJsonCount(1, 'data.shop_orders');
        $this->assertSame([$items[0]->id], $cart->items()->pluck('id')->all());
        $this->assertSame(0, Voucher::where('code', 'SHOPA5K')->value('used_count'));
        $this->assertSame(2, VoucherRedemption::count());
    }

    public function test_cancellation_releases_shop_vouchers_per_part_and_the_platform_voucher_only_when_all_parts_are_cancelled(): void
    {
        [$buyer, $address, , $products] = $this->cart(['100000.00', '200000.00']);
        $shopVoucher = Voucher::factory()->forShop($products[0]->shop)->create(['code' => 'SHOPA5K', 'value' => '5000.00', 'usage_limit' => 1]);
        $platform = Voucher::factory()->create(['code' => 'SAN10K', 'value' => '10000.00', 'per_user_limit' => 1]);
        $order = Order::findOrFail($this->checkout($address, ['platform_voucher' => 'SAN10K', 'shop_vouchers' => [$products[0]->shop_id => 'SHOPA5K']])->assertCreated()->json('data.id'));
        [$first, $second] = $order->shopOrders()->orderBy('id')->get()->all();
        $cancel = fn ($part) => $this->postJson('/api/v1/orders/'.$order->id.'/shop-orders/'.$part->id.'/cancel', ['expected_status' => 'PENDING', 'reason' => 'Đổi ý']);

        $cancel($first)->assertOk();
        $this->assertSame([0, 1], [$shopVoucher->fresh()->used_count, $platform->fresh()->used_count]);
        $this->assertNotNull(VoucherRedemption::where('shop_order_id', $first->id)->value('released_at'));
        $this->assertNull(VoucherRedemption::whereNull('shop_order_id')->value('released_at'));
        // The platform share of the remaining part is not redistributed.
        $this->assertSame('6780.00', $second->fresh()->discount_platform);
        $this->getJson('/api/v1/orders/'.$order->id)->assertOk()->assertJsonPath('data.payable_amount', '193220.00');
        // Retrying the same cancellation releases nothing a second time.
        $cancel($first)->assertOk();
        $this->assertSame([0, 1], [$shopVoucher->fresh()->used_count, $platform->fresh()->used_count]);
        // The released shop voucher (usage_limit 1) can be used again; the platform one is still held by this buyer.
        $this->preview(['platform_voucher' => 'SAN10K'])->assertJsonPath('data.errors.platform_voucher', 'Bạn đã dùng hết số lượt cho mã này.');

        // The seller cancels the last part: now the platform voucher is released too, once.
        $this->login($second->shop->owner);
        $sellerCancel = fn () => $this->patchJson('/api/v1/seller/orders/'.$second->id.'/status', ['expected_status' => 'PENDING', 'status' => 'CANCELLED', 'reason' => 'Hết hàng', 'failure_category' => 'OUT_OF_STOCK']);
        $sellerCancel()->assertOk();
        $sellerCancel()->assertOk();
        $this->assertSame([0, 0], [$shopVoucher->fresh()->used_count, $platform->fresh()->used_count]);
        $this->assertSame(0, VoucherRedemption::whereNull('released_at')->count());
        $this->assertSame(2, VoucherRedemption::count());
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'voucher.released')->where('entity_id', $platform->id)->count());
        // Stored discounts stay as history.
        $this->assertSame('15000.00', $order->fresh()->discount_amount);
        $this->login($buyer);
        $this->preview(['platform_voucher' => 'SAN10K'])->assertOk();
    }

    public function test_zalopay_is_charged_the_discounted_total_and_seller_revenue_stays_the_subtotal(): void
    {
        config(['zalopay.app_id' => '123456', 'zalopay.key1' => bin2hex(random_bytes(32)), 'zalopay.key2' => bin2hex(random_bytes(32)), 'zalopay.create_url' => 'https://sb-openapi.zalopay.vn/v2/create', 'zalopay.query_url' => 'https://sb-openapi.zalopay.vn/v2/query', 'zalopay.return_url' => 'http://localhost:5173/payment/result', 'zalopay.callback_url' => null, 'zalopay.expire_seconds' => 900]);
        Http::preventStrayRequests();
        Http::fake(['*/v2/create' => Http::response(['return_code' => 1, 'order_url' => 'https://sbgateway.zalopay.vn/pay'])]);
        [, $address, , $products] = $this->cart(['125000.00', '75000.00']);
        Voucher::factory()->forShop($products[0]->shop)->create(['code' => 'SHOPA5K', 'value' => '5000.00']);
        Voucher::factory()->create(['code' => 'SAN10K', 'value' => '10000.00']);
        $response = $this->checkout($address, ['platform_voucher' => 'SAN10K', 'shop_vouchers' => [$products[0]->shop_id => 'SHOPA5K']], null, 'ZALOPAY')->assertCreated()->assertJsonPath('data.total_amount', '185000.00');
        $order = Order::with('shopOrders', 'payments')->findOrFail($response->json('data.id'));
        $this->assertSame('185000.00', $order->payments->sole()->amount);
        $this->assertSame('185000.00', $order->shopOrders->reduce(fn ($sum, $part) => bcadd($sum, $part->total_amount, 2), '0.00'));
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/v2/create') && (int) $request['amount'] === 185000);

        // Revenue is SUM(subtotal) of completed shop orders, before any voucher.
        $part = $order->shopOrders->firstWhere('shop_id', $products[0]->shop_id);
        $part->update(['status' => 'COMPLETED', 'completed_at' => now()]);
        $this->login($products[0]->shop->owner);
        $this->getJson('/api/v1/seller/dashboard')->assertOk()->assertJsonPath('data.total_revenue', '125000.00');
    }

    public function test_recommendations_still_count_orders_placed_with_a_voucher(): void
    {
        [$buyer, $address, , $products] = $this->cart(['100000.00']);
        $other = Product::factory()->active()->create(['published_at' => now()->subDays(5)]);
        Voucher::factory()->create(['code' => 'SAN10K', 'value' => '10000.00']);
        $products[0]->update(['published_at' => now()->subDays(5), 'stock_quantity' => 5]);
        $this->checkout($address, ['platform_voucher' => 'SAN10K'])->assertCreated();
        // The ordered product is now the popular one for guests and the buyer's category signal.
        $this->login($buyer);
        $mine = $this->getJson('/api/v1/recommendations')->assertOk();
        $this->assertSame((string) $products[0]->id, $mine->json('data.0.id'));
        $this->assertStringStartsWith('Vì bạn quan tâm', $mine->json('data.0.reason_label'));
        $this->app['session']->flush();
        Auth::forgetGuards();
        $guest = $this->getJson('/api/v1/recommendations')->assertOk();
        $this->assertSame([(string) $products[0]->id, (string) $other->id], $guest->json('data.*.id'));
        $this->assertSame('Đang được nhiều người quan tâm', $guest->json('data.0.reason_label'));
    }

    public function test_sellers_manage_only_their_own_shop_vouchers(): void
    {
        $shop = Shop::factory()->create();
        $foreign = Voucher::factory()->forShop(Shop::factory()->create())->create(['code' => 'FOREIGN']);
        $platform = Voucher::factory()->create(['code' => 'PLATFORM1']);
        $payload = ['code' => ' shop20k ', 'type' => 'FIXED', 'value' => '20000', 'min_order' => '100000', 'usage_limit' => 10, 'per_user_limit' => 1, 'starts_at' => now()->subDay()->toISOString(), 'ends_at' => now()->addMonth()->toISOString()];
        $this->postJson('/api/v1/seller/vouchers', $payload)->assertUnauthorized();
        $this->login(User::factory()->create());
        $this->getJson('/api/v1/seller/vouchers')->assertNotFound();

        $this->login($shop->owner);
        $created = $this->postJson('/api/v1/seller/vouchers', $payload)->assertCreated()
            ->assertJsonPath('data.code', 'SHOP20K')->assertJsonPath('data.scope', 'SHOP')->assertJsonPath('data.shop.id', (string) $shop->id)
            ->assertJsonPath('data.value', '20000.00')->assertJsonPath('data.state', 'ACTIVE')->assertJsonPath('data.used_count', 0)->assertJsonPath('data.can_manage', true)->json('data.id');
        $this->assertSame($shop->owner_id, Voucher::findOrFail($created)->created_by);
        // The client cannot choose scope, shop or counters.
        foreach (['scope' => 'PLATFORM', 'shop_id' => $foreign->shop_id, 'used_count' => 0, 'created_by' => 1] as $field => $value) {
            $this->postJson('/api/v1/seller/vouchers', [...$payload, 'code' => 'OTHER1', $field => $value])->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        foreach ([['code' => 'ab'], ['code' => 'has space'], ['code' => 'SHOP20K'], ['type' => 'BOGUS'], ['value' => '0'], ['value' => '-5'], ['value' => '1.234'], ['type' => 'PERCENT', 'value' => '101'], ['type' => 'PERCENT', 'value' => '0.5'], ['max_discount' => '1000'], ['usage_limit' => 0], ['per_user_limit' => 0], ['ends_at' => now()->subDays(2)->toISOString()]] as $bad) {
            $this->postJson('/api/v1/seller/vouchers', [...$payload, 'code' => 'VALID123', ...$bad])->assertUnprocessable();
        }
        $this->postJson('/api/v1/seller/vouchers', [...$payload, 'code' => 'PCT15', 'type' => 'PERCENT', 'value' => '15', 'max_discount' => '30000'])->assertCreated()->assertJsonPath('data.max_discount', '30000.00');
        $this->getJson('/api/v1/seller/vouchers')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.code', 'PCT15');

        // Another shop's voucher and a platform voucher do not exist for this seller.
        foreach ([$foreign, $platform] as $row) {
            $this->patchJson('/api/v1/seller/vouchers/'.$row->id, ['ends_at' => now()->addYear()->toISOString()])->assertNotFound();
            $this->deleteJson('/api/v1/seller/vouchers/'.$row->id)->assertNotFound();
        }
        foreach (['get' => '/api/v1/admin/vouchers', 'post' => '/api/v1/admin/vouchers'] as $method => $url) {
            $this->json($method, $url, $payload)->assertForbidden();
        }
        $this->patchJson('/api/v1/admin/vouchers/'.$platform->id, ['status' => 'INACTIVE', 'expected_status' => 'ACTIVE'])->assertForbidden();
        $this->assertSame(['ACTIVE', 'ACTIVE'], [$foreign->fresh()->status, $platform->fresh()->status]);

        // Status changes need the status the client saw; "delete" switches the voucher off and is idempotent.
        $this->patchJson('/api/v1/seller/vouchers/'.$created, ['status' => 'INACTIVE'])->assertUnprocessable();
        $this->patchJson('/api/v1/seller/vouchers/'.$created, ['status' => 'INACTIVE', 'expected_status' => 'INACTIVE'])->assertConflict();
        $this->patchJson('/api/v1/seller/vouchers/'.$created, ['status' => 'INACTIVE', 'expected_status' => 'ACTIVE'])->assertOk()->assertJsonPath('data.state', 'INACTIVE');
        $this->patchJson('/api/v1/seller/vouchers/'.$created, ['status' => 'ACTIVE', 'expected_status' => 'INACTIVE'])->assertOk();
        $this->deleteJson('/api/v1/seller/vouchers/'.$created)->assertOk()->assertJsonPath('data.status', 'INACTIVE');
        $this->deleteJson('/api/v1/seller/vouchers/'.$created)->assertOk();
        $this->assertDatabaseHas('vouchers', ['id' => $created, 'status' => 'INACTIVE']);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'voucher.deactivated')->where('entity_id', $created)->count());
    }

    public function test_a_used_voucher_keeps_its_terms_and_admins_manage_platform_vouchers(): void
    {
        $admin = User::factory()->admin()->create();
        $shopVoucher = Voucher::factory()->forShop(Shop::factory()->create())->create(['code' => 'SHOPONLY']);
        $this->login($admin);
        $payload = ['code' => 'SAN50K', 'type' => 'FIXED', 'value' => '50000.00', 'usage_limit' => 3, 'starts_at' => now()->subDay()->toISOString(), 'ends_at' => now()->addMonth()->toISOString()];
        $id = $this->postJson('/api/v1/admin/vouchers', $payload)->assertCreated()->assertJsonPath('data.scope', 'PLATFORM')->assertJsonPath('data.shop', null)->json('data.id');
        // An admin sees shop vouchers but cannot change them.
        $this->getJson('/api/v1/admin/vouchers')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/admin/vouchers?scope=SHOP')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'SHOPONLY')->assertJsonPath('data.0.can_manage', false);
        $this->patchJson('/api/v1/admin/vouchers/'.$shopVoucher->id, ['ends_at' => now()->addYear()->toISOString()])->assertNotFound();
        $this->deleteJson('/api/v1/admin/vouchers/'.$shopVoucher->id)->assertNotFound();

        // Unused: every term may change.
        $this->patchJson('/api/v1/admin/vouchers/'.$id, ['value' => '40000.00', 'min_order' => '100000.00'])->assertOk()->assertJsonPath('data.value', '40000.00')->assertJsonPath('data.has_redemptions', false);

        // Used twice (one later released): terms are frozen, three fields stay editable.
        $voucher = Voucher::findOrFail($id);
        foreach ([null, now()] as $released) {
            VoucherRedemption::create(['voucher_id' => $id, 'user_id' => User::factory()->create()->id, 'order_id' => Order::factory()->create()->id, 'discount_amount' => '40000.00', 'released_at' => $released]);
        }
        $voucher->update(['used_count' => 1]);
        foreach ([['value' => '1.00'], ['type' => 'PERCENT', 'value' => '40'], ['min_order' => '0.00'], ['code' => 'RENAMED'], ['per_user_limit' => 1], ['starts_at' => now()->subYear()->toISOString()]] as $change) {
            $this->patchJson('/api/v1/admin/vouchers/'.$id, $change)->assertUnprocessable();
        }
        // Sending an unchanged term together with an allowed change is fine.
        $this->patchJson('/api/v1/admin/vouchers/'.$id, ['value' => '40000', 'ends_at' => now()->addMonths(2)->toISOString(), 'usage_limit' => 1])->assertOk()->assertJsonPath('data.usage_limit', 1)->assertJsonPath('data.state', 'EXHAUSTED')->assertJsonPath('data.has_redemptions', true);
        $this->patchJson('/api/v1/admin/vouchers/'.$id, ['usage_limit' => 0])->assertUnprocessable();
        $voucher->update(['used_count' => 2, 'usage_limit' => 5]);
        $this->patchJson('/api/v1/admin/vouchers/'.$id, ['usage_limit' => 1])->assertUnprocessable()->assertJsonValidationErrors('usage_limit');
        $this->patchJson('/api/v1/admin/vouchers/'.$id, ['usage_limit' => null])->assertOk()->assertJsonPath('data.usage_limit', null);
        $this->patchJson('/api/v1/admin/vouchers/'.$id, ['ends_at' => now()->subYears(2)->toISOString()])->assertUnprocessable();
        $this->deleteJson('/api/v1/admin/vouchers/'.$id)->assertOk()->assertJsonPath('data.status', 'INACTIVE');
        $this->assertSame('40000.00', $voucher->fresh()->value);
        $this->assertDatabaseCount('vouchers', 2);
    }

    public function test_voucher_lists_and_preview_use_a_constant_number_of_queries_and_preview_is_rate_limited(): void
    {
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->create();
        Voucher::factory()->forShop($shop)->create();
        Voucher::factory()->create();
        $count = function (User $user, string $url): int {
            $this->login($user);
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson($url)->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };
        $few = [$count($admin, '/api/v1/admin/vouchers?per_page=100'), $count($shop->owner, '/api/v1/seller/vouchers?per_page=100')];
        foreach (range(1, 8) as $index) {
            $row = Voucher::factory()->forShop($shop)->create();
            Voucher::factory()->forShop(Shop::factory()->create())->create();
            Voucher::factory()->create();
            VoucherRedemption::create(['voucher_id' => $row->id, 'user_id' => $admin->id, 'order_id' => Order::factory()->create()->id, 'discount_amount' => '1.00']);
        }
        $this->assertSame($few, [$count($admin, '/api/v1/admin/vouchers?per_page=100'), $count($shop->owner, '/api/v1/seller/vouchers?per_page=100')]);

        [, , , $products] = $this->cart(['100000.00', '100000.00', '100000.00']);
        $body = ['platform_voucher' => Voucher::where('scope', 'PLATFORM')->value('code'), 'shop_vouchers' => [$products[0]->shop_id => 'NOPE']];
        $queries = function () use ($body): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->preview($body)->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };
        $small = $queries();
        foreach (range(1, 5) as $index) {
            CartItem::factory()->for(Cart::first())->create();
        }
        $this->assertSame($small, $queries());
        $this->assertSame(0, VoucherRedemption::where('discount_amount', '!=', '1.00')->count());
        // 30 previews a minute per user.
        foreach (range(1, 28) as $index) {
            $this->preview($body)->assertOk();
        }
        $this->preview($body)->assertStatus(429);
        $this->postJson('/api/v1/checkout/preview', ['unknown' => 1])->assertStatus(429);
    }

    public function test_preview_requires_sign_in_and_validates_its_input(): void
    {
        $this->preview()->assertUnauthorized();
        [, , $cart] = $this->cart(['100000.00']);
        $this->preview()->assertOk()->assertJsonPath('data.total', '100000.00')->assertJsonPath('data.discount_total', '0.00');
        foreach ([['unknown' => 1], ['cart_item_ids' => []], ['cart_item_ids' => ['x']], ['platform_voucher' => str_repeat('A', 21)], ['shop_vouchers' => 'X'], ['shop_vouchers' => ['x' => 'CODE']]] as $bad) {
            $this->preview($bad)->assertUnprocessable();
        }
        // Another buyer's cart item is simply not part of this buyer's preview.
        $foreign = CartItem::factory()->create();
        $this->preview(['cart_item_ids' => [$foreign->id]])->assertOk()->assertJsonCount(0, 'data.shop_orders')->assertJsonPath('data.total', '0.00');
        $this->assertSame(1, $cart->items()->count());
    }
}
