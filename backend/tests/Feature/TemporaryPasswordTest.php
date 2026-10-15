<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\TemporaryPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TemporaryPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/', 'Accept' => 'application/json']);
        Notification::fake();
    }

    /** Requests a temporary password and returns it as it appears in the mail. */
    private function issue(User $user): string
    {
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();
        $password = null;
        Notification::assertSentTo($user, TemporaryPasswordNotification::class, function (TemporaryPasswordNotification $notification) use ($user, &$password): bool {
            $password = trim(collect($notification->toMail($user)->introLines)->first(fn (string $line): bool => str_starts_with($line, '**')), '*');

            return true;
        });

        return $password;
    }

    private function signOut(): void
    {
        $this->postJson('/api/v1/auth/logout');
        $this->app['session']->flush();
        Auth::forgetGuards();
    }

    public function test_the_temporary_password_signs_in_once_and_then_a_new_password_is_required(): void
    {
        $user = User::factory()->create(['password' => 'OldSecurePassword123']);
        $temporary = $this->issue($user);

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => $temporary])->assertOk()->assertJsonPath('data.must_change_password', true);
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.must_change_password', true);
        $this->assertNull($user->refresh()->temporary_password);
        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'users', 'entity_id' => $user->id, 'action' => 'user.temporary_password_used']);

        // No current password is asked for, but the usual password rules apply.
        $this->patchJson('/api/v1/auth/password', ['password' => 'short1', 'password_confirmation' => 'short1'])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->patchJson('/api/v1/auth/password', ['password' => 'BrandNewPassword456', 'password_confirmation' => 'BrandNewPassword456'])->assertOk()->assertJsonPath('data.must_change_password', false);
        $this->assertTrue(Hash::check('BrandNewPassword456', $user->refresh()->password));
        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'users', 'entity_id' => $user->id, 'action' => 'user.password_changed']);

        // Used up: the temporary password never works again.
        $this->signOut();
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => $temporary])->assertUnprocessable();
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'BrandNewPassword456'])->assertOk()->assertJsonPath('data.must_change_password', false);
    }

    public function test_the_old_password_keeps_working_and_drops_a_pending_temporary_one(): void
    {
        $user = User::factory()->create(['password' => 'OldSecurePassword123']);
        $temporary = $this->issue($user);
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'OldSecurePassword123'])->assertOk()->assertJsonPath('data.must_change_password', false);
        $this->assertNull($user->refresh()->temporary_password);
        $this->signOut();
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => $temporary])->assertUnprocessable();
    }

    public function test_an_expired_or_wrong_temporary_password_and_a_suspended_account_cannot_sign_in(): void
    {
        $user = User::factory()->create(['password' => 'OldSecurePassword123']);
        $temporary = $this->issue($user);
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Wrong'.$temporary])->assertUnprocessable();
        $this->travel(61)->minutes();
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => $temporary])->assertUnprocessable();
        $this->assertFalse($user->refresh()->must_change_password);

        $this->travelBack();
        $temporary = $this->issue($user);
        $user->forceFill(['status' => UserStatus::SUSPENDED])->save();
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => $temporary])->assertUnprocessable();
    }

    public function test_a_normal_password_change_asks_for_the_current_password(): void
    {
        $this->patchJson('/api/v1/auth/password', ['password' => 'BrandNewPassword456', 'password_confirmation' => 'BrandNewPassword456'])->assertUnauthorized();
        $user = User::factory()->create(['password' => 'OldSecurePassword123']);
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'OldSecurePassword123'])->assertOk();
        $new = ['password' => 'BrandNewPassword456', 'password_confirmation' => 'BrandNewPassword456'];
        $this->patchJson('/api/v1/auth/password', $new)->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->patchJson('/api/v1/auth/password', [...$new, 'current_password' => 'NotTheRightOne123'])->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->patchJson('/api/v1/auth/password', ['current_password' => 'OldSecurePassword123', 'password' => 'OldSecurePassword123', 'password_confirmation' => 'OldSecurePassword123'])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->patchJson('/api/v1/auth/password', [...$new, 'current_password' => 'OldSecurePassword123'])->assertOk();
        $this->assertTrue(Hash::check('BrandNewPassword456', $user->refresh()->password));
    }
}
