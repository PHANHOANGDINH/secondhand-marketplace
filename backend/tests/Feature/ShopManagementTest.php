<?php

namespace Tests\Feature;

use App\Enums\ShopStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Shop;
use App\Models\User;
use App\Services\AuditService;
use App\Services\ShopService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ShopManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeaders(['Origin' => 'http://localhost:5173']);
    }

    private function loginAs(User $user): void
    {
        $this->app['session']->flush();
        Auth::forgetGuards();
        $this->actingAs($user, 'web');
    }

    public function test_create_pending_shop_unique_owner_and_stable_slug(): void
    {
        $user = User::factory()->create();
        $this->loginAs($user);
        $shop = $this->postJson('/api/v1/shops', ['name' => 'Do Cu Phat'])->assertCreated()->assertJsonPath('data.status', 'PENDING')->json('data');
        $this->assertSame(UserRole::USER, $user->refresh()->role);
        $this->getJson('/api/v1/seller/shop')->assertOk()->assertJsonPath('data.id', $shop['id']);
        $this->patchJson('/api/v1/seller/shop', ['name' => 'Another Name'])->assertOk()->assertJsonPath('data.slug', $shop['slug']);
        $this->postJson('/api/v1/shops', ['name' => 'Second Shop'])->assertConflict();
        Shop::find($shop['id'])->delete();
        $this->postJson('/api/v1/shops', ['name' => 'Still Second'])->assertConflict();
    }

    public function test_slug_collision_reserves_archived_slug(): void
    {
        Shop::factory()->create(['slug' => 'do-cu-phat'])->delete();
        $this->loginAs(User::factory()->create());
        $this->postJson('/api/v1/shops', ['name' => 'Do Cu Phat'])->assertCreated()->assertJsonPath('data.slug', 'do-cu-phat-2');
    }

    public function test_shop_rejects_owner_status_and_media_injection(): void
    {
        $user = User::factory()->create();
        $this->loginAs($user);
        $this->postJson('/api/v1/shops', ['name' => 'Test Shop', 'status' => 'ACTIVE', 'owner_id' => 999])->assertUnprocessable()->assertJsonValidationErrors(['status', 'owner_id']);
        Shop::factory()->for($user, 'owner')->create();
        foreach (['status' => 'ACTIVE', 'owner_id' => 999, 'slug' => 'hijack', 'deleted_at' => '2026-01-01', 'logo_path' => '../../private'] as $key => $value) {
            $this->patchJson('/api/v1/seller/shop', [$key => $value])->assertUnprocessable()->assertJsonValidationErrors($key);
        }
    }

    public function test_shop_policy_and_seller_endpoint_enforce_ownership(): void
    {
        $shop = Shop::factory()->create();
        $other = User::factory()->admin()->create();
        $this->loginAs($other);
        $this->assertFalse(Gate::forUser($other)->allows('update', $shop));
        $this->assertFalse(Gate::forUser($other)->allows('view', $shop));
        $this->patchJson('/api/v1/seller/shop', ['name' => 'Hijack'])->assertNotFound();
        $this->assertNotSame('Hijack', $shop->refresh()->name);
        $this->loginAs($shop->owner);
        $shop->update(['status' => ShopStatus::SUSPENDED]);
        $this->getJson('/api/v1/seller/shop')->assertOk();
        $this->patchJson('/api/v1/seller/shop', ['name' => 'Blocked'])->assertForbidden();
    }

    public function test_public_shops_hide_inactive_owners_and_internal_fields(): void
    {
        $active = Shop::factory()->create(['moderation_reason' => 'Private note']);
        $this->getJson('/api/v1/shops/'.$active->slug)->assertOk()->assertJsonMissingPath('data.owner_id')->assertJsonMissingPath('data.moderation_reason')->assertJsonMissingPath('data.deleted_at');
        foreach ([ShopStatus::PENDING, ShopStatus::SUSPENDED, ShopStatus::REJECTED] as $status) {
            $shop = Shop::factory()->create(['status' => $status]);
            $this->getJson('/api/v1/shops/'.$shop->slug)->assertNotFound();
        }
        $this->getJson('/api/v1/shops')->assertJsonCount(1, 'data');
        $active->owner->forceFill(['status' => UserStatus::SUSPENDED])->save();
        $this->getJson('/api/v1/shops/'.$active->slug)->assertNotFound();
        $this->getJson('/api/v1/shops?per_page=101')->assertUnprocessable();
        $this->getJson('/api/v1/shops?status=PENDING')->assertUnprocessable();
    }

    public function test_admin_transition_matrix_and_owner_resubmit(): void
    {
        $shop = Shop::factory()->create(['status' => ShopStatus::PENDING]);
        $admin = User::factory()->admin()->create();
        $this->loginAs($admin);
        $this->getJson('/api/v1/admin/shops')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/admin/shops/'.$shop->id)->assertOk();
        foreach ([['PENDING', 'REJECTED'], ['PENDING', 'ACTIVE'], ['ACTIVE', 'SUSPENDED'], ['SUSPENDED', 'ACTIVE']] as [$from,$to]) {
            if ($from === 'PENDING' && $shop->refresh()->status === ShopStatus::REJECTED) {
                $this->loginAs($shop->owner);
                $this->patchJson('/api/v1/seller/shop', ['description' => 'Revised information'])->assertOk();
                $this->postJson('/api/v1/seller/shop/submit')->assertOk()->assertJsonPath('data.status', 'PENDING');
                $this->loginAs($admin);
            }
            $this->patchJson('/api/v1/admin/shops/'.$shop->id.'/status', ['expected_status' => $from, 'status' => $to, 'reason' => 'Reviewed evidence'])->assertOk()->assertJsonPath('data.status', $to);
        }
        $this->assertDatabaseCount('audit_logs', 5);
        $this->assertSame($shop->owner_id, $shop->refresh()->owner_id);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $admin->id, 'entity_id' => $shop->id, 'from_status' => 'SUSPENDED', 'to_status' => 'ACTIVE']);
    }

    public function test_admin_invalid_stale_self_moderation_and_inactive_owner_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->create(['status' => ShopStatus::PENDING]);
        $this->loginAs($admin);
        $url = '/api/v1/admin/shops/'.$shop->id.'/status';
        $this->patchJson($url, ['expected_status' => 'PENDING', 'status' => 'SUSPENDED', 'reason' => 'Invalid'])->assertConflict();
        $this->patchJson($url, ['expected_status' => 'ACTIVE', 'status' => 'SUSPENDED', 'reason' => 'Stale'])->assertConflict();
        $this->patchJson($url, ['expected_status' => 'PENDING', 'status' => 'ACTIVE'])->assertUnprocessable();
        $shop->owner->forceFill(['status' => UserStatus::SUSPENDED])->save();
        $this->patchJson($url, ['expected_status' => 'PENDING', 'status' => 'ACTIVE', 'reason' => 'Blocked owner'])->assertConflict();
        $own = Shop::factory()->for($admin, 'owner')->create(['status' => ShopStatus::PENDING]);
        $this->assertFalse(Gate::forUser($admin)->allows('moderate', $own));
        $this->patchJson('/api/v1/admin/shops/'.$own->id.'/status', ['expected_status' => 'PENDING', 'status' => 'ACTIVE', 'reason' => 'Self approve'])->assertForbidden();
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_regular_user_cannot_access_admin_shop_endpoints(): void
    {
        $shop = Shop::factory()->create();
        $this->loginAs($shop->owner);
        $this->getJson('/api/v1/admin/shops')->assertForbidden();
        $this->getJson('/api/v1/admin/shops/'.$shop->id)->assertForbidden();
        $this->patchJson('/api/v1/admin/shops/'.$shop->id.'/status', ['status' => 'ACTIVE'])->assertForbidden();
    }

    public function test_moderation_rolls_back_if_audit_write_fails(): void
    {
        $admin = User::factory()->admin()->create();
        $shop = Shop::factory()->create(['status' => ShopStatus::PENDING]);
        $this->mock(AuditService::class)->shouldReceive('record')->once()->andThrow(new \RuntimeException('audit unavailable'));
        try {
            app(ShopService::class)->moderate($admin, $shop, ['expected_status' => 'PENDING', 'status' => 'ACTIVE', 'reason' => 'Review']);
            $this->fail('Expected failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('audit unavailable', $e->getMessage());
        }
        $this->assertSame(ShopStatus::PENDING, $shop->refresh()->status);
    }

    public function test_shop_search_treats_zero_and_wildcards_as_literal_text(): void
    {
        $literal = Shop::factory()->create(['name' => 'Store 0%_!']);
        Shop::factory()->create(['name' => 'Unrelated Store']);
        foreach (['0', '%', '_', '!'] as $search) {
            $this->getJson('/api/v1/shops?search='.rawurlencode($search))
                ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', (string) $literal->id);
        }
        $this->loginAs(User::factory()->admin()->create());
        $this->getJson('/api/v1/admin/shops?search=0')->assertOk()->assertJsonCount(1, 'data');
    }
}
