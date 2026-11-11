<?php

namespace Tests\Feature;

use App\Enums\ShopStatus;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ShopLogoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/seller/shop']);
    }

    private function seller(ShopStatus $status = ShopStatus::ACTIVE): array
    {
        $owner = User::factory()->create();
        $shop = Shop::factory()->create(['owner_id' => $owner->id, 'status' => $status]);

        return [$owner, $shop];
    }

    private function upload(?UploadedFile $file = null)
    {
        return $this->post('/api/v1/seller/shop/logo', ['avatar' => $file ?? UploadedFile::fake()->image('logo.png', 800, 500)], ['Accept' => 'application/json']);
    }

    public function test_the_owner_sets_a_redrawn_square_logo_with_an_audit(): void
    {
        [$owner, $shop] = $this->seller();
        $this->actingAs($owner, 'web');

        $response = $this->upload()->assertOk();

        $path = $shop->refresh()->logo_path;
        $this->assertStringStartsWith("shop-logos/{$shop->id}/", $path);
        $this->assertStringContainsString($path, $response->json('data.logo_url'));
        [$width, $height, $type] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame([512, 512, IMAGETYPE_JPEG], [$width, $height, $type]);
        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'shops', 'entity_id' => $shop->id, 'action' => 'shop.updated', 'reason' => 'fields: logo_path']);
    }

    public function test_everyone_sees_the_logo_on_the_public_shop(): void
    {
        [$owner, $shop] = $this->seller();
        $this->actingAs($owner, 'web');
        $this->upload()->assertOk();
        $url = $this->getJson('/api/v1/seller/shop')->assertOk()->json('data.logo_url');
        $this->assertNotNull($url);

        $this->app['auth']->forgetGuards();
        $this->getJson("/api/v1/shops/{$shop->slug}")->assertOk()->assertJsonPath('data.logo_url', $url);
        $this->assertContains($url, array_column($this->getJson('/api/v1/shops')->assertOk()->json('data'), 'logo_url'));
    }

    public function test_a_public_product_carries_the_picture_of_its_shop(): void
    {
        [$owner, $shop] = $this->seller();
        $product = Product::factory()->active()->create(['shop_id' => $shop->id]);
        $this->actingAs($owner, 'web');
        $url = $this->upload()->assertOk()->json('data.logo_url');

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/products/'.$product->slug)->assertOk()->assertJsonPath('data.shop.logo_url', $url);
    }

    public function test_a_shop_without_a_picture_has_no_url(): void
    {
        [, $shop] = $this->seller();

        $this->getJson("/api/v1/shops/{$shop->slug}")->assertOk()->assertJsonPath('data.logo_url', null);
    }

    public function test_a_new_logo_deletes_the_old_file_and_removing_clears_it(): void
    {
        [$owner, $shop] = $this->seller();
        $this->actingAs($owner, 'web');
        $this->upload()->assertOk();
        $first = $shop->refresh()->logo_path;

        $this->upload()->assertOk();
        $second = $shop->refresh()->logo_path;
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        $this->deleteJson('/api/v1/seller/shop/logo')->assertOk()->assertJsonPath('data.logo_url', null);
        $this->assertNull($shop->refresh()->logo_path);
        Storage::disk('public')->assertMissing($second);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_removing_when_there_is_no_logo_writes_no_audit(): void
    {
        [$owner] = $this->seller();
        $this->actingAs($owner, 'web');

        $this->deleteJson('/api/v1/seller/shop/logo')->assertOk();

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_a_suspended_shop_cannot_change_its_picture(): void
    {
        [$owner, $shop] = $this->seller(ShopStatus::SUSPENDED);
        $this->actingAs($owner, 'web');

        $this->upload()->assertForbidden();
        $this->deleteJson('/api/v1/seller/shop/logo')->assertForbidden();

        $this->assertNull($shop->refresh()->logo_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_a_guest_or_a_user_without_a_shop_cannot_set_one(): void
    {
        [, $shop] = $this->seller();
        $this->upload()->assertUnauthorized();

        $this->actingAs(User::factory()->create(), 'web');
        $this->upload()->assertNotFound();

        $this->assertNull($shop->refresh()->logo_path);
    }

    public function test_an_admin_with_no_shop_does_not_reach_another_shop_through_this_route(): void
    {
        [, $shop] = $this->seller();
        $this->actingAs(User::factory()->create(['role' => UserRole::ADMIN]), 'web');

        $this->upload()->assertNotFound();

        $this->assertNull($shop->refresh()->logo_path);
    }

    public function test_files_that_are_not_pictures_are_refused(): void
    {
        [$owner, $shop] = $this->seller();
        $this->actingAs($owner, 'web');

        $this->upload(UploadedFile::fake()->createWithContent('fake.jpg', '<?php echo 1;'))->assertUnprocessable()->assertJsonValidationErrors('avatar');
        $this->upload(UploadedFile::fake()->create('big.png', 3000, 'image/png'))->assertUnprocessable();

        $this->assertNull($shop->refresh()->logo_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_the_path_still_cannot_be_set_through_the_shop_form(): void
    {
        [$owner, $shop] = $this->seller();
        $this->actingAs($owner, 'web');

        $this->patchJson('/api/v1/seller/shop', ['logo_path' => '../../private'])->assertUnprocessable();

        $this->assertNull($shop->refresh()->logo_path);
    }
}
