<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_returns_the_public_api_contract_without_database(): void
    {
        $this->getJson('/api/v1/health')->assertOk()->assertExactJson([
            'status' => 'ok',
            'message' => 'Secondhand Marketplace API is running',
        ]);
    }

    public function test_frontend_origin_supports_credentialed_requests(): void
    {
        $this->withHeaders(['Origin' => 'http://localhost:5173'])
            ->getJson('/api/v1/health')
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_untrusted_origin_is_not_allowed(): void
    {
        $response = $this->withHeaders(['Origin' => 'https://untrusted.example'])
            ->getJson('/api/v1/health')->assertOk();

        $this->assertNotContains($response->headers->get('Access-Control-Allow-Origin'), ['*', 'https://untrusted.example']);
    }
}
