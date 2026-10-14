<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShopProvinceTest extends TestCase
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

    private function product(?string $province, array $attributes = []): Product
    {
        return Product::factory()->active()->create(['shop_id' => Shop::factory()->create(['province_code' => $province])->id, ...$attributes]);
    }

    public function test_province_list_has_the_34_current_units_and_three_featured_cities(): void
    {
        $rows = $this->getJson('/api/v1/provinces')->assertOk()->json('data');
        $this->assertCount(34, $rows);
        $this->assertCount(34, array_unique(array_column($rows, 'code')));
        $this->assertSame(['01' => 'Hà Nội', '48' => 'Đà Nẵng', '79' => 'TP. Hồ Chí Minh'], collect($rows)->where('featured', true)->sortBy('code')->pluck('name', 'code')->all());
        foreach ($rows as $row) {
            $this->assertMatchesRegularExpression('/^\d{2}$/', $row['code']);
            $this->assertNotSame('', $row['name']);
        }
    }

    public function test_seller_chooses_a_province_from_the_list_when_creating_and_editing_a_shop(): void
    {
        $owner = User::factory()->create();
        $this->login($owner);
        foreach (['00', '99', 'HCM', 79.5, ['79']] as $bad) {
            $this->postJson('/api/v1/shops', ['name' => 'Tiệm thử', 'province_code' => $bad])->assertUnprocessable()->assertJsonValidationErrors('province_code');
        }
        $this->postJson('/api/v1/shops', ['name' => 'Tiệm thử', 'province_code' => '79'])->assertCreated()
            ->assertJsonPath('data.province_code', '79')->assertJsonPath('data.province_name', 'TP. Hồ Chí Minh');
        $this->patchJson('/api/v1/seller/shop', ['province_code' => 'XX'])->assertUnprocessable();
        // A code that disappeared in the 2025 merger (old Lào Cai, 10) is refused; the current code is 15.
        $this->patchJson('/api/v1/seller/shop', ['province_code' => '10'])->assertUnprocessable();
        $this->patchJson('/api/v1/seller/shop', ['province_code' => '15'])->assertOk()->assertJsonPath('data.province_code', '15')->assertJsonPath('data.province_name', 'Lào Cai');
        $this->assertSame('fields: province_code', DB::table('audit_logs')->where('action', 'shop.updated')->latest('id')->value('reason'));
        $this->patchJson('/api/v1/seller/shop', ['province_code' => null])->assertOk()->assertJsonPath('data.province_code', null)->assertJsonPath('data.province_name', null);
        // Changing the area alone does not send the shop back to moderation.
        $this->assertSame('PENDING', Shop::sole()->status->value);

        $shop = Shop::sole();
        $shop->update(['province_code' => '48', 'status' => 'ACTIVE']);
        $this->login(User::factory()->admin()->create());
        $this->getJson('/api/v1/admin/shops/'.$shop->id)->assertOk()->assertJsonPath('data.province_code', '48')->assertJsonPath('data.province_name', 'Đà Nẵng');
        $this->getJson('/api/v1/shops/'.$shop->slug)->assertOk()->assertJsonPath('data.province_name', 'Đà Nẵng');
    }

    public function test_public_products_can_be_filtered_by_the_shops_province_and_expose_its_name_without_extra_queries(): void
    {
        $hcm = $this->product('79');
        $hanoi = $this->product('01');
        $unset = $this->product(null);
        $ids = fn (string $query = ''): array => $this->getJson('/api/v1/products?per_page=100'.$query)->assertOk()->json('data.*.id');
        $this->assertEqualsCanonicalizing([(string) $hcm->id, (string) $hanoi->id, (string) $unset->id], $ids());
        $this->assertSame([(string) $hcm->id], $ids('&province=79'));
        $this->assertSame([], $ids('&province=48'));
        foreach (['00', 'abc', '7'] as $bad) {
            $this->getJson('/api/v1/products?province='.$bad)->assertUnprocessable()->assertJsonValidationErrors('province');
        }
        $this->getJson('/api/v1/products/'.$hcm->slug)->assertOk()->assertJsonPath('data.shop.province_code', '79')->assertJsonPath('data.shop.province_name', 'TP. Hồ Chí Minh');
        $this->getJson('/api/v1/products/'.$unset->slug)->assertOk()->assertJsonPath('data.shop.province_code', null)->assertJsonPath('data.shop.province_name', null);
        // The filter composes with the others.
        $cheap = $this->product('79', ['price' => '50.00']);
        $this->assertSame([(string) $cheap->id], $ids('&province=79&max_price=60'));

        $count = function (string $url): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson($url)->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };
        $urls = ['/api/v1/products?per_page=100', '/api/v1/products?per_page=100&province=79'];
        $few = array_map($count, $urls);
        foreach (range(1, 10) as $index) {
            $this->product($index % 2 ? '79' : '31');
        }
        $this->assertSame($few, array_map($count, $urls));
    }

    public function test_recommendations_and_similar_products_filter_by_province_only_when_asked(): void
    {
        $this->freezeTime();
        $hcm = $this->product('79', ['price' => '100000.00']);
        $hanoi = $this->product('01', ['price' => '100000.00', 'category_id' => $hcm->category_id]);
        $alsoHcm = $this->product('79', ['price' => '110000.00', 'category_id' => $hcm->category_id]);
        Wishlist::factory()->count(2)->create(['product_id' => $hanoi->id]);

        $all = $this->getJson('/api/v1/recommendations')->assertOk()->json('data.*.id');
        $this->assertSame((string) $hanoi->id, $all[0]);
        $this->assertCount(3, $all);
        $this->assertEqualsCanonicalizing([(string) $hcm->id, (string) $alsoHcm->id], $this->getJson('/api/v1/recommendations?province=79')->assertOk()->json('data.*.id'));
        $this->assertSame([], $this->getJson('/api/v1/recommendations?province=48')->assertOk()->json('data'));
        $this->getJson('/api/v1/recommendations?province=zz')->assertUnprocessable();
        // The unfiltered ranking keeps its own cache entry and is not replaced by a filtered one.
        $this->assertSame($all, $this->getJson('/api/v1/recommendations')->assertOk()->json('data.*.id'));
        $this->assertTrue(Cache::has('recommendations:v1:guest'));
        $this->assertTrue(Cache::has('recommendations:v1:guest:p79'));

        $similar = '/api/v1/products/'.$hcm->slug.'/similar';
        $this->assertEqualsCanonicalizing([(string) $hanoi->id, (string) $alsoHcm->id], $this->getJson($similar)->assertOk()->json('data.*.id'));
        $this->assertSame([(string) $alsoHcm->id], $this->getJson($similar.'?province=79')->assertOk()->json('data.*.id'));
        $this->getJson($similar.'?province=79')->assertJsonPath('data.0.shop.province_name', 'TP. Hồ Chí Minh');
    }
}
