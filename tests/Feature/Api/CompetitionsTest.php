<?php

namespace Tests\Feature\Api;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * Covers the cache-served competitions API endpoints (Phase 2, commit 2):
 * GET /api/competitions and GET /api/competitions/{id}. Asserts the standard
 * envelope (data + meta.{lastUpdated,stale,cached}), normalization via
 * Normalizer, cache-hit behavior (one upstream call), the 503 hard-miss path,
 * stale last-known-good fallback, and that the X-Auth-Token header is sent.
 *
 * No RefreshDatabase: these endpoints only touch the cache (array store per
 * phpunit.xml) and the HTTP client, never the database.
 */
class CompetitionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Deterministic token + base URL; never hit the wire, never really sleep.
        Config::set('football.token', 'test-token');
        Config::set('football.base_url', 'https://api.football-data.org/v4');

        Http::preventStrayRequests();
        Sleep::fake();
    }

    /**
     * Minimal upstream /competitions payload: a featured cup (WC) and a league
     * (PL) so a single fixture exercises both `kind` branches and the featured
     * meta flag.
     *
     * @return array<string, mixed>
     */
    private function upstreamCompetitions(): array
    {
        return [
            'count' => 2,
            'competitions' => [
                [
                    'id' => 2000,
                    'area' => ['name' => 'World'],
                    'name' => 'FIFA World Cup',
                    'code' => 'WC',
                    'type' => 'CUP',
                    'emblem' => 'https://crests.football-data.org/WC.png',
                ],
                [
                    'id' => 2021,
                    'area' => ['name' => 'England'],
                    'name' => 'Premier League',
                    'code' => 'PL',
                    'type' => 'LEAGUE',
                    'emblem' => 'https://crests.football-data.org/PL.png',
                ],
            ],
        ];
    }

    /**
     * Single upstream /competitions/{code} payload (PL).
     *
     * @return array<string, mixed>
     */
    private function upstreamCompetitionPL(): array
    {
        return [
            'id' => 2021,
            'area' => ['name' => 'England'],
            'name' => 'Premier League',
            'code' => 'PL',
            'type' => 'LEAGUE',
            'emblem' => 'https://crests.football-data.org/PL.png',
        ];
    }

    // --- 1. index happy path: 200, fresh meta, normalized items --------------

    public function test_index_returns_normalized_competitions_with_fresh_envelope(): void
    {
        Http::fake([
            '*/competitions' => Http::response($this->upstreamCompetitions(), 200),
        ]);

        $response = $this->getJson('/api/competitions');

        $response->assertOk();

        // Envelope meta: first call is a fresh upstream fill.
        $response->assertJsonPath('meta.stale', false);
        $response->assertJsonPath('meta.cached', false);
        $this->assertIsString($response->json('meta.lastUpdated'));

        // data is a JSON array of normalized competitions.
        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertCount(2, $data);
        $this->assertArrayNotHasKey('competitions', $data, 'data must be a list, not the raw upstream envelope');

        // WC: featured cup, short + color from config meta.
        $wc = collect($data)->firstWhere('code', 'WC');
        $this->assertSame('2000', $wc['id']);
        $this->assertSame('FIFA World Cup', $wc['name']);
        $this->assertSame('World Cup', $wc['short']);
        $this->assertSame('World', $wc['region']);
        $this->assertSame('cup', $wc['kind']);
        $this->assertTrue($wc['featured']);
        $this->assertSame('https://crests.football-data.org/WC.png', $wc['emblem']);

        // PL: non-featured league, short name from config meta.
        $pl = collect($data)->firstWhere('code', 'PL');
        $this->assertSame('2021', $pl['id']);
        $this->assertSame('Premier Lg', $pl['short']);
        $this->assertSame('England', $pl['region']);
        $this->assertSame('league', $pl['kind']);
        $this->assertFalse($pl['featured']);
        $this->assertSame('https://crests.football-data.org/PL.png', $pl['emblem']);
    }

    // --- 2. cache hit: second request serves from cache, one upstream call ---

    public function test_index_serves_second_request_from_cache_with_one_upstream_call(): void
    {
        Http::fake([
            '*/competitions' => Http::response($this->upstreamCompetitions(), 200),
        ]);

        $first = $this->getJson('/api/competitions');
        $second = $this->getJson('/api/competitions');

        // Only the first request reaches upstream; the second is served from cache.
        Http::assertSentCount(1);

        $first->assertOk()->assertJsonPath('meta.cached', false);
        $second->assertOk()->assertJsonPath('meta.cached', true);
        $second->assertJsonPath('meta.stale', false);

        // Same payload from both calls.
        $this->assertSame($first->json('data'), $second->json('data'));
    }

    // --- 3. show happy path: single normalized competition ------------------

    public function test_show_returns_single_normalized_competition(): void
    {
        Http::fake([
            '*/competitions/PL' => Http::response($this->upstreamCompetitionPL(), 200),
        ]);

        $response = $this->getJson('/api/competitions/PL');

        $response->assertOk();
        $response->assertJsonPath('meta.stale', false);
        $response->assertJsonPath('meta.cached', false);
        $this->assertIsString($response->json('meta.lastUpdated'));

        // data is a single normalized competition object (associative, not a list).
        $response->assertJsonPath('data.id', '2021');
        $response->assertJsonPath('data.code', 'PL');
        $response->assertJsonPath('data.name', 'Premier League');
        $response->assertJsonPath('data.short', 'Premier Lg');
        $response->assertJsonPath('data.region', 'England');
        $response->assertJsonPath('data.kind', 'league');
        $response->assertJsonPath('data.featured', false);
        $response->assertJsonPath('data.emblem', 'https://crests.football-data.org/PL.png');
        $response->assertJsonPath('data.color', '#37003C');
    }

    // --- 4. hard miss: 429 with empty cache -> 503, null data, stale --------

    public function test_index_returns_503_on_hard_miss_with_empty_cache(): void
    {
        // Default retries=2 => 3 upstream attempts (retries + 1) before the retry
        // helper throws RequestException, which the service catches and returns null.
        // With no last-known-good cached, cached() yields data=null => envelope 503.
        Http::fake([
            '*/competitions' => Http::sequence()
                ->push(['error' => 'rate limited'], 429)
                ->push(['error' => 'rate limited'], 429)
                ->push(['error' => 'rate limited'], 429)
                ->whenEmpty(Http::response(['unexpected' => true], 500)),
        ]);

        $response = $this->getJson('/api/competitions');

        $response->assertStatus(503);
        $response->assertJsonPath('data', null);
        $response->assertJsonPath('meta.stale', true);
        $response->assertJsonPath('meta.cached', false);
        $response->assertJsonPath('meta.lastUpdated', null);

        // Three upstream attempts on the single failing call; backoff slept twice.
        Http::assertSentCount(3);
        Sleep::assertSleptTimes(2);
    }

    // --- 5. stale fallback: prime success, then upstream fails -> 200 stale --

    public function test_index_serves_stale_last_good_with_200_after_upstream_failure(): void
    {
        // Phase 1: one fresh 200 fill (populates fresh + last-good keys).
        // Phase 2: persistent 429 across 3 attempts (retries + 1) -> last-good served.
        Http::fake([
            '*/competitions' => Http::sequence()
                ->push($this->upstreamCompetitions(), 200)
                ->push(['error' => 'rate limited'], 429)
                ->push(['error' => 'rate limited'], 429)
                ->push(['error' => 'rate limited'], 429)
                ->whenEmpty(Http::response(['unexpected' => true], 500)),
        ]);

        $primed = $this->getJson('/api/competitions');
        $primed->assertOk()->assertJsonPath('meta.stale', false);

        // Expire ONLY the fresh key so the next request must go upstream, but the
        // last-known-good payload remains for the stale fallback.
        Cache::forget('fd:competitions');

        $response = $this->getJson('/api/competitions');

        // Stale fallback still returns the normalized last-good payload at 200.
        $response->assertOk();
        $response->assertJsonPath('meta.stale', true);
        $response->assertJsonPath('meta.cached', true);
        $this->assertIsString($response->json('meta.lastUpdated'));

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertSame($primed->json('data'), $data);
        $this->assertSame('PL', collect($data)->firstWhere('code', 'PL')['code']);
    }

    // --- 6. auth header: X-Auth-Token sent on the upstream call --------------

    public function test_index_sends_auth_token_header_to_upstream(): void
    {
        Http::fake([
            '*/competitions' => Http::response($this->upstreamCompetitions(), 200),
        ]);

        $this->getJson('/api/competitions')->assertOk();

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('X-Auth-Token', 'test-token')
            && str_contains($request->url(), 'api.football-data.org/v4/competitions'));
    }
}
