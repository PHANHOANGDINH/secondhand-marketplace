<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/']);
    }

    private function registration(array $overrides = []): array
    {
        $password = Str::random(24).'a1';

        return array_replace(['name' => 'Auth Test User', 'email' => 'new-user@example.test',
            'password' => $password, 'password_confirmation' => $password], $overrides);
    }

    public function test_registration_creates_safe_hashed_user_without_a_session(): void
    {
        $payload = $this->registration(['email' => ' NEW-USER@EXAMPLE.TEST ']);
        $this->get('/sanctum/csrf-cookie')->assertNoContent();
        $response = $this->postJson('/api/v1/auth/register', $payload)->assertCreated()
            ->assertJsonPath('data.email', 'new-user@example.test')->assertJsonPath('data.role', 'USER')
            ->assertJsonPath('data.status', 'ACTIVE');
        $user = User::where('email', 'new-user@example.test')->firstOrFail();
        $this->assertTrue(Hash::check($payload['password'], $user->password));
        $this->assertNotSame($payload['password'], $user->password);
        $this->assertGuest('web');
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
        foreach (['password', 'remember_token', 'deleted_at'] as $field) {
            $response->assertJsonMissingPath('data.'.$field);
        }
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_registration_rejects_role_and_status_escalation(): void
    {
        $this->postJson('/api/v1/auth/register', $this->registration(['role' => 'ADMIN', 'status' => 'SUSPENDED']))
            ->assertUnprocessable()->assertJsonValidationErrors(['role', 'status']);
        $this->assertDatabaseCount('users', 0);
        $this->assertGuest('web');
    }

    public function test_registration_rejects_invalid_input(): void
    {
        $this->postJson('/api/v1/auth/register', $this->registration(['name' => 'x', 'email' => 'invalid', 'password' => 'short', 'password_confirmation' => 'different']))
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'password']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_rejects_duplicate_email_including_deleted_users(): void
    {
        $user = User::factory()->create(['email' => 'existing@example.test']);
        $this->postJson('/api/v1/auth/register', $this->registration(['email' => 'EXISTING@example.test']))
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $user->delete();
        $this->postJson('/api/v1/auth/register', $this->registration(['email' => 'existing@example.test']))
            ->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_password_exceeding_bcrypt_byte_limit_is_rejected(): void
    {
        $password = str_repeat('a', 72).'1';
        $this->postJson('/api/v1/auth/register', $this->registration(['password' => $password, 'password_confirmation' => $password]))
            ->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_valid_login_rotates_session_and_supports_remember_cookie(): void
    {
        $password = Str::random(24);
        $user = User::factory()->create(['password' => $password]);
        $this->get('/sanctum/csrf-cookie');
        $oldSession = session()->getId();
        $this->postJson('/api/v1/auth/login', ['email' => strtoupper($user->email), 'password' => $password, 'remember' => true])
            ->assertOk()->assertJsonPath('data.id', (string) $user->id)->assertCookie(Auth::guard('web')->getRecallerName());
        $this->assertNotSame($oldSession, session()->getId());
        $this->assertAuthenticatedAs($user, 'web');
        $this->getJson('/api/v1/auth/me')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_invalid_unknown_suspended_and_deleted_logins_have_same_error(): void
    {
        $password = Str::random(24);
        $user = User::factory()->create(['password' => $password]);
        $bad = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertUnprocessable()->json('errors');
        $this->assertSame($bad, $this->postJson('/api/v1/auth/login', ['email' => 'unknown@example.test', 'password' => $password])->assertUnprocessable()->json('errors'));
        $user->status = UserStatus::SUSPENDED;
        $user->save();
        $this->assertSame($bad, $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => $password])->assertUnprocessable()->json('errors'));
        $user->status = UserStatus::ACTIVE;
        $user->save();
        $user->delete();
        $this->assertSame($bad, $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => $password])->assertUnprocessable()->json('errors'));
        $this->assertGuest('web');
    }

    public function test_guest_cannot_access_private_routes(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
    }

    public function test_me_only_returns_current_safe_user(): void
    {
        $user = User::factory()->create();
        User::factory()->create();
        $response = $this->actingAs($user, 'web')->getJson('/api/v1/auth/me')->assertOk()
            ->assertJsonPath('data.id', (string) $user->id)->assertJsonPath('data.email', $user->email);
        foreach (['password', 'remember_token', 'deleted_at'] as $field) {
            $response->assertJsonMissingPath('data.'.$field);
        }
    }

    public function test_logout_invalidates_session_and_next_me_is_unauthorized(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web')->getJson('/api/v1/auth/me')->assertOk();
        $oldSession = session()->getId();
        $oldCsrf = session()->token();
        $this->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->assertGuest('web');
        $this->assertNotSame($oldSession, session()->getId());
        $this->assertNotSame($oldCsrf, session()->token());
        Auth::forgetGuards();
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_suspended_session_cannot_read_me_but_can_logout(): void
    {
        $user = User::factory()->create(['status' => UserStatus::SUSPENDED]);
        $this->actingAs($user, 'web')->getJson('/api/v1/auth/me')->assertForbidden();
        $this->postJson('/api/v1/auth/logout')->assertNoContent();
    }

    public function test_login_rate_limit_uses_normalized_email_and_ip(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'LIMIT@example.test', 'password' => 'wrong'])->assertUnprocessable();
        }
        $this->postJson('/api/v1/auth/login', ['email' => ' limit@example.test ', 'password' => 'wrong'])->assertStatus(429)->assertHeader('Retry-After');
        $this->postJson('/api/v1/auth/login', ['email' => 'different@example.test', 'password' => 'wrong'])->assertUnprocessable();
    }

    public function test_register_rate_limit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/register', [])->assertUnprocessable();
        }
        $this->postJson('/api/v1/auth/register', [])->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_csrf_cookie_and_exact_cors_origin(): void
    {
        $response = $this->get('/sanctum/csrf-cookie')->assertNoContent()->assertCookie('XSRF-TOKEN')
            ->assertCookie(config('session.cookie'))->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
        $sessionCookie = collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));
        $this->assertTrue($sessionCookie->isHttpOnly());
        $this->assertFalse($sessionCookie->isSecure());
        $this->assertSame('lax', $sessionCookie->getSameSite());
        $this->assertNull($sessionCookie->getDomain());
        $this->assertSame(['localhost:5173'], config('sanctum.stateful'));
        $untrusted = $this->withHeaders(['Origin' => 'https://untrusted.example'])->get('/sanctum/csrf-cookie');
        $this->assertNotContains($untrusted->headers->get('Access-Control-Allow-Origin'), ['*', 'https://untrusted.example']);
    }

    public function test_csrf_middleware_rejects_mutation_when_test_bypass_is_disabled(): void
    {
        $this->app->bind(PreventRequestForgery::class, function ($app) {
            return new class($app, $app['encrypter']) extends PreventRequestForgery
            {
                protected function runningUnitTests(): bool
                {
                    return false;
                }
            };
        });
        $this->postJson('/api/v1/auth/login', ['email' => 'missing@example.test', 'password' => 'wrong'])->assertStatus(419);
    }

    public function test_non_stateful_login_is_rejected_without_creating_session(): void
    {
        $this->withHeaders(['Origin' => 'https://untrusted.example', 'Referer' => 'https://untrusted.example/'])
            ->postJson('/api/v1/auth/login', ['email' => 'missing@example.test', 'password' => 'wrong'])->assertStatus(419);
    }
}
