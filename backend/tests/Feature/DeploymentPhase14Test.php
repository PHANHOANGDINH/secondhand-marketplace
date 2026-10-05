<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DeploymentPhase14Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Route::get('/api/v1/phase14-proxy-probe', function (Request $request) {
            return response()->json(['secure' => $request->isSecure(), 'host' => $request->getHost(), 'port' => $request->getPort(), 'ip' => $request->ip(), 'url' => $request->url()]);
        });
    }

    private function proxyHeaders(string $source): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => $source])->withHeaders([
            'Host' => 'backend.internal:9000',
            'X-Forwarded-Host' => 'marketplace.example.com',
            'X-Forwarded-Port' => '443',
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-For' => '198.51.100.14',
            'X-Forwarded-Prefix' => '/injected',
        ]);
    }

    public function test_only_configured_proxy_can_forward_https_host_port_and_client_ip(): void
    {
        config(['trustedproxy.proxies' => ['172.30.14.10']]);
        $this->proxyHeaders('172.30.14.10');
        $this->getJson('http://backend.internal:9000/api/v1/phase14-proxy-probe')->assertOk()->assertExactJson([
            'secure' => true, 'host' => 'marketplace.example.com', 'port' => 443,
            'ip' => '198.51.100.14', 'url' => 'https://marketplace.example.com/api/v1/phase14-proxy-probe',
        ]);
    }

    public function test_untrusted_client_cannot_spoof_forwarded_https_or_identity(): void
    {
        config(['trustedproxy.proxies' => ['172.30.14.10']]);
        $this->proxyHeaders('203.0.113.14');
        $this->getJson('http://backend.internal:9000/api/v1/phase14-proxy-probe')->assertOk()->assertExactJson([
            'secure' => false, 'host' => 'backend.internal', 'port' => 9000,
            'ip' => '203.0.113.14', 'url' => 'http://backend.internal:9000/api/v1/phase14-proxy-probe',
        ]);
    }

    public function test_default_empty_proxy_list_does_not_trust_an_arbitrary_upstream(): void
    {
        config(['trustedproxy.proxies' => []]);
        $this->proxyHeaders('172.30.14.10');
        $this->getJson('http://backend.internal:9000/api/v1/phase14-proxy-probe')->assertOk()->assertJsonPath('secure', false)
            ->assertJsonPath('host', 'backend.internal')->assertJsonPath('ip', '172.30.14.10');
    }
}
