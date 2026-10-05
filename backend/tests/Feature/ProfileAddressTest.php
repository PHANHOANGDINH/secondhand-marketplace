<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Address;
use App\Models\User;
use App\Services\AddressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ProfileAddressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeaders(['Origin' => 'http://localhost:5173']);
    }

    private function loginAs(User $user): void
    {
        Auth::forgetGuards();
        $this->actingAs($user, 'web');
    }

    private function addressData(array $extra = []): array
    {
        return array_replace(['recipient_name' => 'Test User', 'phone' => '0901234567', 'address_line' => '10 Test Street', 'ward' => 'Test Ward', 'province' => 'Test Province', 'country_code' => 'VN'], $extra);
    }

    public function test_guest_and_suspended_accounts_cannot_use_profile_or_addresses(): void
    {
        foreach (['/profile', '/addresses'] as $url) {
            $this->getJson('/api/v1'.$url)->assertUnauthorized();
        }
        $this->postJson('/api/v1/addresses', $this->addressData())->assertUnauthorized();
        $this->loginAs(User::factory()->create(['status' => UserStatus::SUSPENDED]));
        $this->patchJson('/api/v1/profile', ['name' => 'Blocked'])->assertForbidden();
        $this->postJson('/api/v1/addresses', $this->addressData())->assertForbidden();
    }

    public function test_profile_update_only_changes_allowed_fields(): void
    {
        $user = User::factory()->create();
        $this->loginAs($user);
        $this->getJson('/api/v1/profile')->assertOk()->assertJsonPath('data.email', $user->email)->assertJsonMissingPath('data.password');
        $this->patchJson('/api/v1/profile', ['name' => 'Updated Name', 'phone' => '+84 901234567'])->assertOk()->assertJsonPath('data.name', 'Updated Name');
        $this->assertSame('+84 901234567', $user->refresh()->phone);
        $this->patchJson('/api/v1/profile', ['phone' => null])->assertOk()->assertJsonPath('data.phone', null);
    }

    public function test_profile_rejects_privilege_credentials_and_path_injection(): void
    {
        $user = User::factory()->create();
        $this->loginAs($user);
        foreach (['role' => 'ADMIN', 'status' => 'SUSPENDED', 'email' => 'change@example.test', 'password' => 'replacement', 'email_verified_at' => '2026-01-01', 'deleted_at' => '2026-01-01', 'avatar_path' => '../../private'] as $field => $value) {
            $this->patchJson('/api/v1/profile', [$field => $value])->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertSame(UserRole::USER, $user->refresh()->role);
        $this->patchJson('/api/v1/profile', ['name' => 'x', 'phone' => 'abc'])->assertUnprocessable()->assertJsonValidationErrors(['name', 'phone']);
    }

    public function test_address_crud_default_switch_and_deterministic_promotion(): void
    {
        $user = User::factory()->create();
        $this->loginAs($user);
        $first = $this->postJson('/api/v1/addresses', $this->addressData(['is_default' => false]))->assertCreated()->assertJsonPath('data.is_default', true)->json('data.id');
        $second = $this->postJson('/api/v1/addresses', $this->addressData())->assertCreated()->assertJsonPath('data.is_default', false)->json('data.id');
        $third = $this->postJson('/api/v1/addresses', $this->addressData(['is_default' => true]))->assertCreated()->assertJsonPath('data.is_default', true)->json('data.id');
        $this->getJson('/api/v1/addresses/'.$first)->assertOk()->assertJsonPath('data.is_default', false)->assertJsonMissingPath('data.user_id');
        $this->patchJson('/api/v1/addresses/'.$second, ['ward' => 'Changed Ward'])->assertOk()->assertJsonPath('data.ward', 'Changed Ward');
        $this->patchJson('/api/v1/addresses/'.$second.'/default')->assertOk()->assertJsonPath('data.is_default', true);
        $this->assertSame(1, $user->addresses()->where('is_default', true)->count());
        $this->deleteJson('/api/v1/addresses/'.$second)->assertNoContent();
        $this->assertTrue(Address::findOrFail($first)->is_default);
        $this->assertFalse(Address::findOrFail($third)->is_default);
        $this->getJson('/api/v1/addresses')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 2);
        $this->patchJson('/api/v1/addresses/'.$first, ['is_default' => false])->assertOk();
        $this->assertSame(1, $user->addresses()->where('is_default', true)->count());
        $this->deleteJson('/api/v1/addresses/'.$first)->assertNoContent();
        $this->deleteJson('/api/v1/addresses/'.$third)->assertNoContent();
        $this->assertDatabaseCount('addresses', 0);
    }

    public function test_cross_user_addresses_are_forbidden_even_for_admin(): void
    {
        $address = Address::factory()->create();
        $other = User::factory()->admin()->create();
        $this->loginAs($other);
        $this->getJson('/api/v1/addresses')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/addresses/'.$address->id)->assertForbidden();
        $this->patchJson('/api/v1/addresses/'.$address->id, ['ward' => 'Wrong'])->assertForbidden();
        $this->deleteJson('/api/v1/addresses/'.$address->id)->assertForbidden();
        $this->patchJson('/api/v1/addresses/'.$address->id.'/default')->assertForbidden();
        foreach (['view', 'update', 'delete', 'setDefault'] as $ability) {
            $this->assertFalse(Gate::forUser($other)->allows($ability, $address));
        }
    }

    public function test_address_validation_and_owner_injection(): void
    {
        $this->loginAs(User::factory()->create());
        $this->postJson('/api/v1/addresses', [])->assertUnprocessable();
        $this->postJson('/api/v1/addresses', $this->addressData(['user_id' => 999, 'country_code' => 'US']))->assertUnprocessable()->assertJsonValidationErrors(['user_id', 'country_code']);
        $this->getJson('/api/v1/addresses?per_page=101')->assertUnprocessable();
    }

    public function test_address_default_changes_rollback_with_outer_transaction(): void
    {
        $user = User::factory()->create();
        $first = Address::factory()->for($user)->create(['is_default' => true]);
        DB::beginTransaction();
        app(AddressService::class)->create($user, $this->addressData(['is_default' => true]));
        DB::rollBack();
        $this->assertTrue($first->refresh()->is_default);
        $this->assertDatabaseCount('addresses', 1);
    }
}
