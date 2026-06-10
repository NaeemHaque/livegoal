<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * Covers HTTP caching + rate limiting on the cached JSON API: successful GETs
 * carry a public Cache-Control (per-endpoint freshness) and an ETag so browsers
 * and a CDN can cache and revalidate (304), and the open API is rate limited.
 */
class CacheHeadersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('football.token', 'test-token');
        Config::set('football.base_url', 'https://api.football-data.org/v4');

        Http::preventStrayRequests();
        Sleep::fake();
    }

    public function test_cacheable_endpoint_sets_public_cache_control_and_etag(): void
    {
        Http::fake(['*/competitions' => Http::response(['competitions' => []], 200)]);

        $response = $this->getJson('/api/competitions');

        $response->assertOk();
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=3600', $cacheControl);
        $this->assertNotNull($response->headers->get('ETag'));
    }

    public function test_volatile_endpoint_uses_a_short_max_age(): void
    {
        Http::fake(['*/matches*' => Http::response(['matches' => []], 200)]);

        $response = $this->getJson('/api/matches');

        $response->assertOk();
        $this->assertStringContainsString('max-age=120', (string) $response->headers->get('Cache-Control'));
    }

    public function test_conditional_get_returns_304_when_unchanged(): void
    {
        Http::fake(['*/competitions' => Http::response(['competitions' => []], 200)]);

        $this->getJson('/api/competitions');                 // fresh fill
        $cached = $this->getJson('/api/competitions');       // cache hit (stable content)
        $etag = $cached->headers->get('ETag');
        $this->assertNotNull($etag);

        $this->getJson('/api/competitions', ['If-None-Match' => $etag])
            ->assertStatus(304);
    }

    public function test_api_responses_carry_rate_limit_headers(): void
    {
        Http::fake(['*/competitions' => Http::response(['competitions' => []], 200)]);

        $this->getJson('/api/competitions')
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', 300);
    }
}
