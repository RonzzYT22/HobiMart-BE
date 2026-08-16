<?php

namespace Tests\Feature;

use Tests\TestCase;

class SetupTest extends TestCase
{
    public function test_cors_allows_frontend_origin(): void
    {
        $response = $this->options('/api/test-cors', [], [
            'Origin' => 'http://localhost:3000',
        ]);

        $response->assertStatus(200)
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');
    }

    public function test_reverb_config_exists(): void
    {
        $this->assertNotEmpty(config('broadcasting.connections.reverb'));
    }

    public function test_tntsearch_driver_configured(): void
    {
        $this->assertEquals('tntsearch', config('scout.driver'));
    }

    public function test_cors_allowed_headers(): void
    {
        $response = $this->options('/api/test-cors', [], [
            'Origin' => 'http://localhost:3000',
            'Access-Control-Request-Method' => 'GET',
            'Access-Control-Request-Headers' => 'Content-Type, Authorization, X-Request-ID',
        ]);

        $response->assertHeader('Access-Control-Allow-Headers', 'content-type, authorization, x-request-id, accept');
    }

    public function test_cors_exposed_headers(): void
    {
        $response = $this->options('/api/test-cors', [], ['Origin' => 'http://localhost:3000']);

        $response->assertHeader('Access-Control-Expose-Headers', 'X-Proxy-Status, X-Encrypted');
    }

    public function test_cors_max_age(): void
    {
        $response = $this->options('/api/test-cors', [], [
            'Origin' => 'http://localhost:3000',
            'Access-Control-Request-Method' => 'GET',
        ]);

        $response->assertHeader('Access-Control-Max-Age', '86400');
    }

    public function test_reverb_port_configuration(): void
    {
        $reverbConfig = config('broadcasting.connections.reverb');
        $this->assertEquals(8080, $reverbConfig['options']['port']);
        $this->assertEquals('http', $reverbConfig['options']['scheme']);
    }

    public function test_tntsearch_fuzzy_settings(): void
    {
        $tntsearchConfig = config('scout.tntsearch');
        $this->assertTrue($tntsearchConfig['fuzziness']);
        $this->assertEquals(2, $tntsearchConfig['fuzzy']['prefix_length']);
        $this->assertEquals(50, $tntsearchConfig['fuzzy']['max_expansions']);
        $this->assertEquals(2, $tntsearchConfig['fuzzy']['distance']);
    }
}