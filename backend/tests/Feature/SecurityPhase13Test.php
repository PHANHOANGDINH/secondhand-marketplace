<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class SecurityPhase13Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/']);
    }

    public function test_rotating_login_emails_and_forwarded_headers_cannot_bypass_ip_limit(): void
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'spray-'.$attempt.'@example.test', 'password' => 'wrong'])->assertUnprocessable();
        }
        $this->withHeaders(['X-Forwarded-For' => '198.51.100.42'])
            ->postJson('/api/v1/auth/login', ['email' => 'fresh@example.test', 'password' => 'wrong'])
            ->assertStatus(429)->assertHeader('Retry-After');
        $this->assertGuest('web');
    }

    public function test_checkout_is_subject_to_the_payment_write_budget(): void
    {
        $buyer = User::factory()->create();
        $address = Address::factory()->for($buyer, 'user')->create();
        $this->actingAs($buyer, 'web');
        for ($attempt = 0; $attempt < 12; $attempt++) {
            $this->withHeader('Idempotency-Key', (string) Str::uuid())
                ->postJson('/api/v1/checkout', ['address_id' => $address->id, 'payment_method' => 'COD'])
                ->assertUnprocessable()->assertJsonValidationErrors('cart');
        }
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/checkout', ['address_id' => $address->id, 'payment_method' => 'COD'])
            ->assertStatus(429)->assertHeader('Retry-After');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_api_internal_errors_are_redacted_even_when_debug_is_enabled(): void
    {
        config(['app.debug' => true]);
        Route::get('/api/v1/phase13-internal-error', function (): never {
            throw new RuntimeException('SQLSTATE private-password private-key C:/private/config.php');
        });
        $this->getJson('/api/v1/phase13-internal-error')->assertStatus(500)
            ->assertExactJson(['message' => 'Server Error']);
        Route::get('/api/v1/phase13-unavailable', function (): never {
            abort(503, 'private gateway configuration', ['Retry-After' => '30']);
        });
        $this->getJson('/api/v1/phase13-unavailable')->assertStatus(503)
            ->assertHeader('Retry-After', '30')->assertExactJson(['message' => 'Server Error']);
    }

    public function test_debug_trace_is_removed_from_api_client_errors_without_changing_business_messages(): void
    {
        config(['app.debug' => true]);
        Route::get('/api/v1/phase13-forbidden', function (): never {
            abort(403, 'You cannot buy from your own shop.');
        });
        $this->getJson('/api/v1/phase13-forbidden')->assertForbidden()
            ->assertExactJson(['message' => 'You cannot buy from your own shop.']);
        $this->getJson('/api/v1/not-a-real-route')->assertNotFound()
            ->assertJsonMissingPath('trace')->assertJsonMissingPath('exception')
            ->assertJsonMissingPath('file')->assertJsonMissingPath('line');
    }
}
