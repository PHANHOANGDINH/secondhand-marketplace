<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use App\Services\EmailVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use ReflectionProperty;
use RuntimeException;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'CorrectHorseBattery123';

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/register']);
    }

    private function register(string $email = 'new-user@example.test'): User
    {
        $this->postJson('/api/v1/auth/register', ['name' => 'Người Mới', 'email' => $email, 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])->assertCreated();

        return User::where('email', $email)->firstOrFail();
    }

    private function mailedUrl(User $user): string
    {
        $url = null;
        Notification::assertSentTo($user, VerifyEmailNotification::class, function (VerifyEmailNotification $notification) use (&$url): bool {
            $url = (new ReflectionProperty($notification, 'url'))->getValue($notification);

            return true;
        });

        return $url;
    }

    private function mailedCode(User $user): string
    {
        $code = null;
        Notification::assertSentTo($user, VerifyEmailNotification::class, function (VerifyEmailNotification $notification) use (&$code): bool {
            $code = (new ReflectionProperty($notification, 'code'))->getValue($notification);

            return true;
        });

        return $code;
    }

    /** The request the frontend page makes from the link in the mail. */
    private function apiPathFrom(string $url): string
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        ['id' => $id, 'hash' => $hash] = $query;
        unset($query['id'], $query['hash']);

        return "/api/v1/auth/email/verify/{$id}/{$hash}?".http_build_query($query);
    }

    private function login(string $email, string $password = self::PASSWORD)
    {
        Auth::forgetGuards();

        return $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => $password]);
    }

    public function test_registering_mails_a_link_to_the_frontend_and_does_not_sign_in(): void
    {
        $user = $this->register();

        $this->assertGuest('web');
        $this->assertNull($user->email_verified_at);
        $url = $this->mailedUrl($user);
        $this->assertStringStartsWith(config('auth.email_verification.url').'?', $url);
        $this->assertStringContainsString('signature=', $url);
        $this->assertStringNotContainsString(self::PASSWORD, $url);
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_an_unverified_account_cannot_sign_in_and_gets_no_session_or_remember_cookie(): void
    {
        $user = $this->register();

        $response = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => self::PASSWORD, 'remember' => true])
            ->assertForbidden()->assertJsonPath('code', 'EMAIL_NOT_VERIFIED');
        $this->assertGuest('web');
        foreach ($response->headers->getCookies() as $cookie) {
            $this->assertStringNotContainsString('remember_', $cookie->getName());
        }
        $this->assertNull($user->refresh()->remember_token);
    }

    public function test_a_wrong_password_never_reveals_that_an_account_is_unverified(): void
    {
        $user = $this->register();

        $this->login($user->email, 'WrongPassword12345')->assertUnprocessable()->assertJsonMissingPath('code');
    }

    public function test_the_link_verifies_once_records_an_audit_and_then_sign_in_works(): void
    {
        $user = $this->register();
        $path = $this->apiPathFrom($this->mailedUrl($user));

        $this->postJson($path)->assertOk();
        $this->assertNotNull($user->refresh()->email_verified_at);
        $this->assertGuest('web');
        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'users', 'entity_id' => $user->id, 'action' => 'user.email_verified']);

        // A second click is harmless and writes nothing new.
        $this->postJson($path)->assertOk();
        $this->assertDatabaseCount('audit_logs', 1);

        $this->login($user->email)->assertOk()->assertJsonPath('data.email', $user->email);
        $this->assertAuthenticatedAs($user->refresh(), 'web');
    }

    public function test_a_get_request_cannot_verify_so_mail_scanners_do_nothing(): void
    {
        $user = $this->register();

        $this->getJson($this->apiPathFrom($this->mailedUrl($user)))->assertStatus(405);
        $this->assertNull($user->refresh()->email_verified_at);
    }

    public function test_tampered_expired_and_foreign_links_are_rejected(): void
    {
        $user = $this->register();
        $other = $this->register('other@example.test');
        $path = $this->apiPathFrom($this->mailedUrl($user));

        $tampered = preg_replace('/signature=[^&]+/', 'signature='.str_repeat('0', 64), $path);
        $this->postJson($tampered)->assertUnprocessable()->assertJsonPath('code', 'EMAIL_VERIFICATION_LINK_INVALID');
        // The signature of one account does not fit another account's id.
        $foreign = str_replace("/verify/{$user->id}/", "/verify/{$other->id}/", $path);
        $this->postJson($foreign)->assertUnprocessable();

        // A correctly signed link whose address no longer matches the account.
        $mismatch = URL::temporarySignedRoute('auth.email-verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1('someone-else@example.test')], false);
        $this->postJson($mismatch)->assertUnprocessable();

        $this->travel((int) config('auth.email_verification.minutes') + 1)->minutes();
        $this->postJson($path)->assertUnprocessable();

        $this->assertNull($user->refresh()->email_verified_at);
        $this->assertNull($other->refresh()->email_verified_at);
    }

    public function test_resend_gives_one_answer_and_only_mails_accounts_waiting_for_verification(): void
    {
        $waiting = $this->register();
        $verified = User::factory()->create(['email' => 'verified@example.test']);
        $old = User::factory()->unverified()->create(['email' => 'old@example.test', 'created_at' => '2026-10-15 06:00:00']);

        $answers = [];
        foreach ([$waiting->email, $verified->email, $old->email, 'nobody@example.test'] as $email) {
            $answers[] = $this->postJson('/api/v1/auth/email/resend', ['email' => $email])->assertOk()->json();
        }
        $this->assertCount(1, array_unique(array_map('json_encode', $answers)));
        Notification::assertSentToTimes($waiting, VerifyEmailNotification::class, 2);
        Notification::assertNotSentTo([$verified, $old], VerifyEmailNotification::class);
    }

    public function test_resend_is_rate_limited(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/email/resend', ['email' => 'limit@example.test'])->assertOk();
        }
        $this->postJson('/api/v1/auth/email/resend', ['email' => 'limit@example.test'])->assertStatus(429);
    }

    public function test_accounts_from_before_the_cutoff_are_exempt_without_changing_their_data(): void
    {
        $old = User::factory()->unverified()->create(['password' => self::PASSWORD, 'created_at' => '2026-10-15 06:00:00']);

        $this->login($old->email)->assertOk();
        $this->assertAuthenticatedAs($old, 'web');
        $this->assertNull($old->refresh()->email_verified_at);
    }

    public function test_the_cutoff_can_be_moved_by_configuration(): void
    {
        config(['auth.email_verification.required_from' => '2026-10-01 00:00:00']);
        $user = User::factory()->unverified()->create(['password' => self::PASSWORD, 'created_at' => '2026-10-15 06:00:00']);

        $this->login($user->email)->assertForbidden();
    }

    public function test_a_temporary_password_proves_the_mailbox_and_verifies_the_account(): void
    {
        $user = $this->register();
        $temporary = 'Temp'.Str::random(20).'2a';
        $user->forceFill(['temporary_password' => Hash::make($temporary), 'temporary_password_expires_at' => now()->addHour()])->save();

        $this->login($user->email, $temporary)->assertOk()->assertJsonPath('data.must_change_password', true);
        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_a_mail_failure_does_not_undo_the_registration(): void
    {
        Notification::shouldReceive('send')->andThrow(new RuntimeException('SMTP down'));

        $user = $this->register();
        $this->assertNull($user->email_verified_at);
        $this->assertDatabaseHas('users', ['email' => 'new-user@example.test']);
    }

    public function test_the_mail_is_in_vietnamese_and_carries_the_link(): void
    {
        $user = $this->register();
        $mail = (new VerifyEmailNotification('https://example.test/verify-email?id=1', 60, '012345', 15))->toMail($user);

        $this->assertStringContainsString('Xác minh thư điện tử', $mail->subject);
        $this->assertSame('https://example.test/verify-email?id=1', $mail->actionUrl);
        $this->assertStringContainsString('012345', implode(' ', $mail->introLines).implode(' ', $mail->outroLines));
    }

    private function postCode(string $email, string $code)
    {
        return $this->postJson('/api/v1/auth/email/verify-code', ['email' => $email, 'code' => $code]);
    }

    public function test_the_code_is_six_digits_kept_only_as_a_hash(): void
    {
        $user = $this->register();
        $code = $this->mailedCode($user);

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertNotSame($code, $user->refresh()->email_verification_code);
        $this->assertTrue(Hash::check($code, $user->email_verification_code));
        $this->assertArrayNotHasKey('email_verification_code', $user->toArray());
    }

    public function test_the_code_verifies_clears_itself_records_an_audit_and_sign_in_works(): void
    {
        $user = $this->register();

        $this->postCode(' NEW-USER@example.test ', $this->mailedCode($user))->assertOk();

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->email_verification_code);
        $this->assertGuest('web');
        $this->assertDatabaseHas('audit_logs', ['entity_id' => $user->id, 'action' => 'user.email_verified', 'reason' => 'code']);
        $this->login($user->email)->assertOk();
    }

    public function test_a_wrong_code_counts_and_gives_the_same_answer_as_an_unknown_account(): void
    {
        $user = $this->register();
        $code = $this->mailedCode($user);
        $wrong = $code === '000000' ? '000001' : '000000';

        $answer = $this->postCode($user->email, $wrong)->assertUnprocessable()->assertJsonPath('code', 'EMAIL_VERIFICATION_CODE_INVALID')->json();
        $this->assertSame($answer, $this->postCode('nobody@example.test', $wrong)->assertUnprocessable()->json());
        $this->assertSame(1, $user->refresh()->email_verification_code_attempts);
        $this->assertNull($user->email_verified_at);
    }

    public function test_the_code_dies_after_too_many_wrong_tries_even_when_the_next_try_is_right(): void
    {
        $user = $this->register();
        $code = $this->mailedCode($user);
        $wrong = $code === '000000' ? '000001' : '000000';
        $service = app(EmailVerificationService::class);

        for ($i = 0; $i < EmailVerificationService::MAX_CODE_ATTEMPTS; $i++) {
            $this->assertFalse($service->verifyCode($user->email, $wrong));
        }
        $this->assertFalse($service->verifyCode($user->email, $code));
        $this->assertNull($user->refresh()->email_verified_at);
    }

    public function test_guessing_is_rate_limited_per_address(): void
    {
        $user = $this->register();
        for ($i = 0; $i < 5; $i++) {
            $this->postCode($user->email, '000000');
        }
        $this->postCode($user->email, '000000')->assertStatus(429);
    }

    public function test_an_expired_code_is_refused(): void
    {
        $user = $this->register();
        $code = $this->mailedCode($user);

        $this->travel((int) config('auth.email_verification.code_minutes') + 1)->minutes();
        $this->postCode($user->email, $code)->assertUnprocessable();
        $this->assertNull($user->refresh()->email_verified_at);
    }

    public function test_a_new_code_replaces_the_old_one(): void
    {
        $user = $this->register();
        $this->postJson('/api/v1/auth/email/resend', ['email' => $user->email])->assertOk();

        $codes = [];
        Notification::assertSentTo($user, VerifyEmailNotification::class, function (VerifyEmailNotification $notification) use (&$codes): bool {
            $codes[] = (new ReflectionProperty($notification, 'code'))->getValue($notification);

            return true;
        });
        $this->assertCount(2, $codes);
        if ($codes[0] !== $codes[1]) {
            $this->postCode($user->email, $codes[0])->assertUnprocessable();
        }
        $this->postCode($user->email, $codes[1])->assertOk();
    }

    public function test_a_badly_formed_code_is_a_validation_error_and_uses_no_try(): void
    {
        $user = $this->register();

        foreach (['12345', '1234567', 'abcdef'] as $bad) {
            $this->postCode($user->email, $bad)->assertUnprocessable()->assertJsonValidationErrors('code');
        }
        $this->assertSame(0, $user->refresh()->email_verification_code_attempts);
    }

    public function test_the_link_also_clears_the_code(): void
    {
        $user = $this->register();

        $this->postJson($this->apiPathFrom($this->mailedUrl($user)))->assertOk();
        $this->assertNull($user->refresh()->email_verification_code);
    }

    public function test_a_verified_or_exempt_account_cannot_use_a_code(): void
    {
        $old = User::factory()->unverified()->create(['created_at' => '2026-10-15 06:00:00']);
        $old->forceFill(['email_verification_code' => Hash::make('123456'), 'email_verification_code_expires_at' => now()->addHour()])->save();

        $this->postCode($old->email, '123456')->assertUnprocessable();
        $this->assertNull($old->refresh()->email_verified_at);
    }
}
