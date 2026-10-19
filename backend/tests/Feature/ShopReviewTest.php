<?php

namespace Tests\Feature;

use App\Enums\ReviewStatus;
use App\Enums\ShopOrderStatus;
use App\Enums\ShopStatus;
use App\Enums\UserStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\ShopReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/** Shop star ratings: buyers with a completed order at the shop, one each, editable; public list with a summary. */
class ShopReviewTest extends TestCase
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

    /** A buyer whose order part at the shop has the given status. */
    private function buyer(Shop $shop, ShopOrderStatus $status = ShopOrderStatus::COMPLETED): User
    {
        $buyer = User::factory()->create();
        $part = ShopOrder::factory()->for(Order::factory()->state(['buyer_id' => $buyer->id]))->create(['shop_id' => $shop->id]);
        $part->forceFill(['status' => $status])->save();

        return $buyer;
    }

    private function url(Shop $shop, string $suffix = ''): string
    {
        return '/api/v1/shops/'.$shop->slug.'/reviews'.$suffix;
    }

    public function test_a_buyer_with_a_completed_order_rates_the_shop_once_and_can_change_it(): void
    {
        $shop = Shop::factory()->create(['status' => ShopStatus::ACTIVE]);
        $buyer = $this->buyer($shop);
        $this->login($buyer);
        $this->getJson($this->url($shop, '/mine'))->assertOk()->assertJsonPath('data.can_review', true)->assertJsonPath('data.review', null)->assertJsonPath('data.own_shop', false);

        $this->putJson($this->url($shop), ['rating' => 4, 'comment' => '  Giao nhanh  '])->assertCreated()
            ->assertJsonPath('data.rating', 4)->assertJsonPath('data.comment', 'Giao nhanh')->assertJsonPath('data.author.name', $buyer->name)
            ->assertJsonPath('summary.average', 4)->assertJsonPath('summary.count', 1);
        $this->putJson($this->url($shop), ['rating' => 5, 'comment' => ''])->assertOk()->assertJsonPath('data.rating', 5)->assertJsonPath('data.comment', null);
        $this->assertSame(1, ShopReview::count());
        $this->getJson($this->url($shop, '/mine'))->assertOk()->assertJsonPath('data.review.rating', 5);
    }

    public function test_public_list_shows_published_ratings_with_average_and_distribution(): void
    {
        $shop = Shop::factory()->create(['status' => ShopStatus::ACTIVE]);
        foreach ([5, 5, 4, 2] as $rating) {
            ShopReview::factory()->create(['shop_id' => $shop->id, 'rating' => $rating]);
        }
        ShopReview::factory()->create(['shop_id' => $shop->id, 'rating' => 1, 'status' => ReviewStatus::HIDDEN]);
        ShopReview::factory()->create(['rating' => 1]);

        $this->getJson($this->url($shop))->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('summary.count', 4)
            ->assertJsonPath('summary.average', 4)
            ->assertJsonPath('summary.distribution', ['5' => 2, '4' => 1, '3' => 0, '2' => 1, '1' => 0])
            ->assertJsonMissingPath('data.0.author.email')
            ->assertJsonPath('meta.per_page', 10);
        // The product page's seller card reads the same shop ratings.
        $product = Product::factory()->active()->create(['shop_id' => $shop->id]);
        $this->getJson('/api/v1/products/'.$product->slug)->assertOk()->assertJsonPath('data.shop.rating_count', 4)->assertJsonPath('data.shop.rating_avg', 4);
    }

    public function test_only_completed_buyers_may_rate_and_never_the_owner(): void
    {
        $shop = Shop::factory()->create(['status' => ShopStatus::ACTIVE]);
        $body = ['rating' => 5];
        $this->putJson($this->url($shop), $body)->assertUnauthorized();
        $this->getJson($this->url($shop, '/mine'))->assertUnauthorized();

        foreach ([ShopOrderStatus::PENDING, ShopOrderStatus::SHIPPING, ShopOrderStatus::CANCELLED] as $status) {
            $this->login($this->buyer($shop, $status));
            $this->getJson($this->url($shop, '/mine'))->assertOk()->assertJsonPath('data.can_review', false);
            $this->putJson($this->url($shop), $body)->assertForbidden()->assertJsonPath('message', 'Chỉ người đã mua và nhận hàng hoàn tất từ gian hàng mới đánh giá được.');
        }
        // A completed order at another shop does not count.
        $this->login($this->buyer(Shop::factory()->create()));
        $this->putJson($this->url($shop), $body)->assertForbidden();

        $this->login($shop->owner);
        $this->getJson($this->url($shop, '/mine'))->assertOk()->assertJsonPath('data.own_shop', true)->assertJsonPath('data.can_review', false);
        $this->putJson($this->url($shop), $body)->assertForbidden();

        $blocked = $this->buyer($shop);
        $blocked->forceFill(['status' => UserStatus::SUSPENDED])->save();
        $this->login($blocked);
        $this->putJson($this->url($shop), $body)->assertForbidden();
        $this->assertSame(0, ShopReview::count());
    }

    public function test_input_is_validated_and_hidden_or_closed_shops_are_not_public(): void
    {
        $shop = Shop::factory()->create(['status' => ShopStatus::ACTIVE]);
        $this->login($this->buyer($shop));
        foreach ([[], ['rating' => 0], ['rating' => 6], ['rating' => 'five'], ['rating' => 5, 'comment' => str_repeat('a', 2001)], ['rating' => 5, 'status' => 'PUBLISHED'], ['rating' => 5, 'user_id' => 1]] as $body) {
            $this->putJson($this->url($shop), $body)->assertUnprocessable();
        }
        $this->assertSame(0, ShopReview::count());

        $suspended = Shop::factory()->create(['status' => ShopStatus::SUSPENDED]);
        $this->getJson($this->url($suspended))->assertNotFound();
        $this->login($this->buyer($suspended));
        $this->putJson($this->url($suspended), ['rating' => 5])->assertNotFound();
    }

    public function test_editing_keeps_a_hidden_rating_hidden(): void
    {
        $shop = Shop::factory()->create(['status' => ShopStatus::ACTIVE]);
        $buyer = $this->buyer($shop);
        ShopReview::factory()->create(['shop_id' => $shop->id, 'user_id' => $buyer->id, 'rating' => 1, 'status' => ReviewStatus::HIDDEN]);
        $this->login($buyer);
        $this->putJson($this->url($shop), ['rating' => 3])->assertOk()->assertJsonPath('data.status', 'HIDDEN');
        $this->getJson($this->url($shop))->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('summary.count', 0)->assertJsonPath('summary.average', null);
    }
}
