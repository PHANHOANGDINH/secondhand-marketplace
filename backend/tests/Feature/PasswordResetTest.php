<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/']);
        Notification::fake();
    }

    private function resetPayload(User $user): array
    {
        return ['email' => $user->email, 'token' => Password::createToken($user),
            'password' => 'NewSecurePassword123', 'password_confirmation' => 'NewSecurePassword123'];
    }

    public function test_known_unknown_deleted_and_broker_throttled_emails_have_identical_generic_responses(): void
    {
        $user = User::factory()->create();
        $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => strtoupper($user->email)])->assertOk()->json();
        $this->assertSame($known, $this->postJson('/api/v1/auth/forgot-password', ['email' => 'unknown@example.test'])->assertOk()->json());
        $this->assertSame($known, $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk()->json());
        Notification::assertSentToTimes($user, ResetPassword::class, 1);
        $user->delete();
        $this->assertSame($known, $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk()->json());
        $this->assertCount(1, $known);
    }

    public function test_email_contains_trusted_spa_url_and_database_stores_only_hashed_token(): void
    {
        config(['auth.password_reset_url' => 'https://marketplace.example.test/reset-password']);
        $user = User::factory()->create();
        $response = $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user, $response): bool {
            $stored = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');
            $this->assertNotSame($notification->token, $stored);
            $this->assertTrue(Hash::check($notification->token, $stored));
            $this->assertStringNotContainsString($notification->token, $response->getContent());
            $url = $notification->toMail($user)->actionUrl;
            $this->assertStringStartsWith('https://marketplace.example.test/reset-password?', $url);
            parse_str(parse_url($url, PHP_URL_QUERY), $query);
            $this->assertSame($user->email, $query['email']);
            $this->assertSame($notification->token, $query['token']);

            return true;
        });
    }

    public function test_delivery_failure_remains_generic_and_does_not_expose_internal_error(): void
    {
        Password::shouldReceive('sendResetLink')->once()->andThrow(new \RuntimeException('PRIVATE SMTP DETAILS'));
        $response = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'delivery@example.test'])->assertOk();
        $this->assertStringNotContainsString('PRIVATE', $response->getContent());
    }

    public function test_success_hashes_password_consumes_token_rotates_remember_token_and_does_not_sign_in(): void
    {
        $user = User::factory()->create(['remember_token' => 'old-remember-token']);
        $payload = $this->resetPayload($user);
        $this->postJson('/api/v1/auth/reset-password', $payload)->assertOk()->assertJsonMissingPath('token');
        $this->assertTrue(Hash::check($payload['password'], $user->fresh()->password));
        $this->assertNotSame('old-remember-token', $user->fresh()->remember_token);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertGuest('web');
        $this->postJson('/api/v1/auth/reset-password', $payload)->assertUnprocessable()->assertJsonValidationErrors('token');
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => $payload['password']])->assertOk();
    }

    public function test_expired_invalid_wrong_email_and_unknown_tokens_fail_without_changing_password(): void
    {
        $user = User::factory()->create();
        $payload = $this->resetPayload($user);
        $oldHash = $user->password;
        foreach ([['token' => 'invalid'], ['email' => 'unknown@example.test']] as $overrides) {
            $this->postJson('/api/v1/auth/reset-password', array_replace($payload, $overrides))->assertUnprocessable()->assertJsonValidationErrors('token');
        }
        $other = User::factory()->create();
        $this->postJson('/api/v1/auth/reset-password', array_replace($payload, ['email' => $other->email]))->assertUnprocessable()->assertJsonValidationErrors('token');
        DB::table('password_reset_tokens')->where('email', $user->email)->update(['created_at' => now()->subMinutes(61)]);
        $this->postJson('/api/v1/auth/reset-password', $payload)->assertUnprocessable()->assertJsonValidationErrors('token');
        $this->assertSame($oldHash, $user->fresh()->password);
    }

    public static function invalidPasswords(): array
    {
        return [['short123', 'short123'], ['NoNumbersHere', 'NoNumbersHere'], ['123456789012', '123456789012'],
            ['ValidPassword123', 'DifferentPassword123'], [str_repeat('é', 36).'a1', str_repeat('é', 36).'a1']];
    }

    #[DataProvider('invalidPasswords')]
    public function test_reset_enforces_existing_registration_password_policy(string $password, string $confirmation): void
    {
        $user = User::factory()->create();
        $payload = array_replace($this->resetPayload($user), ['password' => $password, 'password_confirmation' => $confirmation]);
        $this->postJson('/api/v1/auth/reset-password', $payload)->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_reset_does_not_change_account_status_or_existing_authenticated_identity(): void
    {
        $signedIn = User::factory()->create();
        $user = User::factory()->create(['status' => UserStatus::SUSPENDED]);
        $payload = $this->resetPayload($user);
        $this->actingAs($signedIn, 'web')->postJson('/api/v1/auth/reset-password', $payload)->assertOk();
        $this->assertAuthenticatedAs($signedIn, 'web');
        $this->assertSame(UserStatus::SUSPENDED, $user->fresh()->status);
    }

    public function test_inputs_reject_invalid_email_missing_token_and_privilege_fields(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $user = User::factory()->create();
        $payload = $this->resetPayload($user);
        $this->postJson('/api/v1/auth/reset-password', array_replace($payload, ['token' => '']))->assertUnprocessable()->assertJsonValidationErrors('token');
        $this->postJson('/api/v1/auth/reset-password', array_replace($payload, ['role' => 'ADMIN', 'status' => 'ACTIVE']))->assertUnprocessable()->assertJsonValidationErrors(['role', 'status']);
    }

    public function test_recovery_endpoints_require_spa_session_and_csrf(): void
    {
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
        foreach (['forgot-password', 'reset-password'] as $endpoint) {
            $this->postJson('/api/v1/auth/'.$endpoint, ['email' => 'csrf@example.test'])->assertStatus(419);
        }
    }

    public function test_forgot_password_is_rate_limited_even_for_unknown_accounts(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/forgot-password', ['email' => 'limit@example.test'])->assertOk();
        }
        $this->postJson('/api/v1/auth/forgot-password', ['email' => ' LIMIT@EXAMPLE.TEST '])->assertTooManyRequests()->assertHeader('Retry-After');
    }

    public function test_reset_password_is_rate_limited(): void
    {
        $payload = ['email' => 'limit@example.test', 'token' => 'invalid', 'password' => 'ValidPassword123', 'password_confirmation' => 'ValidPassword123'];
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/reset-password', $payload)->assertUnprocessable();
        }
        $this->postJson('/api/v1/auth/reset-password', $payload)->assertTooManyRequests();
    }
}
