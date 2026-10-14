<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\Bid;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVideo;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AuctionClaimGuardTest extends TestCase
{
    use RefreshDatabase;

    private const MESSAGE = 'Sản phẩm đang được giữ cho đấu giá.';

    private const HOLDING = ['SCHEDULED', 'LIVE', 'AWAITING_CHECKOUT'];

    private const RELEASED = ['CANCELLED', 'NO_WINNER', 'CHECKOUT_EXPIRED', 'ORDER_CREATED'];

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

    private function shop(): Shop
    {
        $shop = Shop::factory()->create();
        $this->login($shop->owner);

        return $shop;
    }

    /** A product with two stored images, optionally held or released by an auction in the named state. */
    private function product(Shop $shop, ?string $auction, string $status = 'ACTIVE'): Product
    {
        $product = Product::factory()->active()->for($shop)->create(['stock_quantity' => 3, 'status' => $status]);
        $product->images()->delete();
        foreach ([true, false] as $index => $primary) {
            $path = 'products/'.$product->id.'/'.Str::uuid().'.png';
            Storage::disk('public')->put($path, UploadedFile::fake()->image('seed.png', 20, 20)->getContent());
            $product->images()->create(['image_path' => $path, 'is_primary' => $primary, 'sort_order' => $index]);
        }
        if ($auction !== null) {
            $this->auction($product, $auction);
        }

        return $product->refresh();
    }

    private function auction(Product $product, string $state): Auction
    {
        $auction = Auction::factory()->for($product)->create(match ($state) {
            'SCHEDULED' => ['status' => 'SCHEDULED', 'starts_at' => now()->addHour(), 'ends_at' => now()->addHours(2)],
            'CANCELLED' => ['status' => 'CANCELLED'],
            'NO_WINNER' => ['status' => 'ENDED', 'starts_at' => now()->subHours(2), 'ends_at' => now()->subHour()],
            default => ['status' => 'LIVE'],
        });
        if (in_array($state, ['AWAITING_CHECKOUT', 'CHECKOUT_EXPIRED', 'ORDER_CREATED'], true)) {
            $bid = Bid::factory()->for($auction)->create();
            $auction->forceFill([
                'status' => 'ENDED', 'winner_user_id' => $bid->user_id, 'winning_bid_id' => $bid->id,
                'winner_checkout_expires_at' => $state === 'CHECKOUT_EXPIRED' ? now()->subMinute() : now()->addHour(),
                'winner_order_id' => $state === 'ORDER_CREATED' ? Order::factory()->create(['buyer_id' => $bid->user_id])->id : null,
            ])->save();
        }
        $this->assertSame($state === 'NO_WINNER' ? 'NO_WINNER' : ($state === 'SCHEDULED' || $state === 'LIVE' || $state === 'CANCELLED' ? null : $state), $auction->refresh()->settlementState());

        return $auction;
    }

    private function url(Product $product): string
    {
        return '/api/v1/seller/products/'.$product->id;
    }

    /** Everything a blocked request must leave untouched. */
    private function state(Product $product): array
    {
        return [
            'product' => (array) DB::table('products')->where('id', $product->id)->first(),
            'images' => DB::table('product_images')->where('product_id', $product->id)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            'files' => Storage::disk('public')->allFiles('products/'.$product->id),
            'audit' => DB::table('audit_logs')->count(),
        ];
    }

    /** @return array<string, callable(Product): TestResponse> */
    private function sellerActions(): array
    {
        $version = fn (Product $p): string => $p->refresh()->updated_at->toISOString();
        $actions = [
            'hide' => fn (Product $p) => $this->postJson($this->url($p).'/hide'),
            'submit' => fn (Product $p) => $this->postJson($this->url($p).'/submit'),
            'reopen' => fn (Product $p) => $this->postJson($this->url($p).'/reopen'),
            'destroy' => fn (Product $p) => $this->deleteJson($this->url($p)),
            'update stock_quantity' => fn (Product $p) => $this->patchJson($this->url($p), ['stock_quantity' => 9, 'expected_updated_at' => $version($p)]),
            'image upload' => fn (Product $p) => $this->post($this->url($p).'/images', ['images' => [UploadedFile::fake()->image('c.png', 20, 20)]]),
            'image delete' => fn (Product $p) => $this->deleteJson($this->url($p).'/images/'.$p->images()->orderByDesc('id')->value('id')),
            'image reorder' => fn (Product $p) => $this->patchJson($this->url($p).'/images/reorder', ['image_ids' => $p->images()->orderByDesc('id')->pluck('id')->all()]),
            'image set primary' => fn (Product $p) => $this->patchJson($this->url($p).'/images/'.$p->images()->where('is_primary', false)->value('id'), ['is_primary' => true]),
        ];
        foreach (['name' => 'Tên mới', 'description' => 'Mô tả mới', 'price' => '250000.00', 'condition' => 'FAIR'] as $field => $value) {
            $actions['update '.$field] = fn (Product $p) => $this->patchJson($this->url($p), [$field => $value]);
        }
        $actions['update category_id'] = fn (Product $p) => $this->patchJson($this->url($p), ['category_id' => Category::factory()->create()->id]);
        $actions['update all fields'] = fn (Product $p) => $this->putJson($this->url($p), ['name' => 'Tên mới', 'description' => 'Mô tả mới', 'category_id' => $p->category_id, 'price' => '250000.00', 'stock_quantity' => 9, 'condition' => 'FAIR', 'expected_updated_at' => $version($p)]);

        return $actions;
    }

    public function test_every_seller_product_and_image_action_is_rejected_while_an_auction_holds_the_product(): void
    {
        $shop = $this->shop();
        foreach (self::HOLDING as $claim) {
            foreach ($this->sellerActions() as $name => $action) {
                $product = $this->product($shop, $claim);
                $before = $this->state($product);
                $action($product)->assertConflict()->assertJsonPath('message', self::MESSAGE);
                $this->assertSame($before, $this->state($product), "$name changed data under a $claim claim");
            }
        }
    }

    public function test_claimed_products_stay_locked_in_any_status_reachable_through_admin_moderation(): void
    {
        $shop = $this->shop();
        // An Admin may take down a claimed listing; the Seller still cannot republish, reopen, edit or delete it.
        foreach (['HIDDEN' => ['submit', 'destroy', 'update name', 'image delete'], 'SOLD' => ['hide', 'update stock_quantity'], 'PENDING' => ['update name'], 'REJECTED' => ['reopen'], 'DRAFT' => ['submit', 'destroy']] as $status => $names) {
            foreach ($names as $name) {
                $product = $this->product($shop, 'LIVE', $status);
                $before = $this->state($product);
                $this->sellerActions()[$name]($product)->assertConflict()->assertJsonPath('message', self::MESSAGE);
                $this->assertSame($before, $this->state($product), "$name changed a $status product under a claim");
            }
        }
    }

    public function test_released_auctions_do_not_block_seller_actions(): void
    {
        $shop = $this->shop();
        $allowed = ['hide', 'destroy', 'update name', 'update price', 'update stock_quantity', 'update all fields', 'image upload', 'image delete', 'image reorder', 'image set primary'];
        foreach (self::RELEASED as $state) {
            foreach ($allowed as $name) {
                $product = $this->product($shop, $state);
                $response = $this->sellerActions()[$name]($product);
                $this->assertContains($response->status(), [200, 201, 204], "$name was blocked by a $state auction: ".$response->getContent());
            }
            foreach (['DRAFT' => 'submit', 'HIDDEN' => 'submit', 'REJECTED' => 'reopen'] as $status => $name) {
                $product = $this->product($shop, $state, $status);
                $this->sellerActions()[$name]($product)->assertOk();
            }
        }
        $this->assertTrue(Product::withTrashed()->whereNotNull('deleted_at')->exists());
    }

    public function test_products_without_any_auction_keep_their_existing_behaviour(): void
    {
        $shop = $this->shop();
        $product = $this->product($shop, null);
        // Listings are not reviewed before sale: an edit keeps a live listing live.
        $this->sellerActions()['update name']($product)->assertOk()->assertJsonPath('data.status', 'ACTIVE');
        $product = $this->product($shop, null);
        $this->sellerActions()['hide']($product)->assertOk()->assertJsonPath('data.status', 'HIDDEN');
        $product = $this->product($shop, null);
        $this->sellerActions()['destroy']($product)->assertNoContent();
        $this->assertSoftDeleted($product);
    }

    public function test_video_actions_remain_available_while_an_auction_holds_the_product(): void
    {
        $shop = $this->shop();
        foreach (self::HOLDING as $claim) {
            $product = $this->product($shop, $claim);
            $base = $this->url($product).'/videos';
            $file = UploadedFile::fake()->createWithContent('review.webm', file_get_contents(__DIR__.'/../Fixtures/product-review.webm'));
            $this->post($base, ['video' => $file, 'title' => 'Video khi đang đấu giá'])->assertCreated();
            $video = ProductVideo::where('product_id', $product->id)->firstOrFail();
            Storage::disk('public')->assertExists($video->path);
            $this->patchJson($base.'/'.$video->id, ['title' => 'Tiêu đề mới'])->assertOk()->assertJsonPath('data.title', 'Tiêu đề mới');
            $this->patchJson($base.'/'.$video->id, ['status' => 'HIDDEN'])->assertOk()->assertJsonPath('data.status', 'HIDDEN');
            $this->patchJson($base.'/'.$video->id, ['status' => 'READY'])->assertOk();
            $this->deleteJson($base.'/'.$video->id)->assertNoContent();
            $this->assertSame('ACTIVE', $product->refresh()->status->value);
        }
    }

    public function test_other_sellers_receive_forbidden_before_any_claim_information(): void
    {
        $product = $this->product($this->shop(), 'LIVE');
        $before = $this->state($product);
        $this->login(Shop::factory()->create()->owner);
        foreach (['hide', 'destroy', 'update name', 'update stock_quantity', 'image upload', 'image delete'] as $name) {
            $this->sellerActions()[$name]($product)->assertForbidden();
        }
        $this->assertSame($before, $this->state($product));
    }

    public function test_admin_moderation_follows_the_d5_rule_for_claimed_products(): void
    {
        // D5: a running auction must be cancelled before the product is taken down; a winner claim does not block it.
        $shop = $this->shop();
        $running = $this->product($shop, 'LIVE');
        $awaiting = $this->product($shop, 'AWAITING_CHECKOUT');
        $this->login(User::factory()->create(['role' => 'ADMIN']));
        $hide = fn (Product $product) => $this->patchJson('/api/v1/admin/products/'.$product->id.'/status', ['expected_status' => 'ACTIVE', 'status' => 'REJECTED', 'reason' => 'Kiểm duyệt']);
        $hide($running)->assertConflict();
        $this->assertSame('ACTIVE', $running->refresh()->status->value);
        $hide($awaiting)->assertOk()->assertJsonPath('data.status', 'REJECTED');
    }

    public function test_seller_resources_expose_the_lock_without_per_product_queries(): void
    {
        $shop = $this->shop();
        $locked = $this->product($shop, 'LIVE');
        $free = $this->product($shop, 'CHECKOUT_EXPIRED');
        $rows = collect($this->getJson('/api/v1/seller/products?per_page=100')->assertOk()->json('data'))->keyBy('id');
        $this->assertTrue($rows[(string) $locked->id]['auction_locked']);
        $this->assertFalse($rows[(string) $locked->id]['stock_editable']);
        $this->assertSame((string) $locked->auctionClaim->id, $rows[(string) $locked->id]['auction']['id']);
        $this->assertFalse($rows[(string) $free->id]['auction_locked']);
        $this->assertNull($rows[(string) $free->id]['auction']);
        $this->getJson($this->url($locked))->assertOk()->assertJsonPath('data.auction_locked', true);
        $this->getJson('/api/v1/seller/inventory')->assertOk()->assertJsonPath('data.0.auction_locked', false);

        $count = function (string $url): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson($url)->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };
        $few = $count('/api/v1/seller/products?per_page=100');
        foreach (Product::factory()->active()->for($shop)->count(18)->create() as $index => $product) {
            if ($index % 2 === 0) {
                Auction::factory()->for($product)->create();
            }
        }
        $this->assertLessThanOrEqual($few + 2, $count('/api/v1/seller/products?per_page=100'));
        $this->getJson('/api/v1/seller/products?per_page=100')->assertJsonCount(20, 'data');
    }
}
