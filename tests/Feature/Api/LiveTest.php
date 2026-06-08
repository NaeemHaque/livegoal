<?php

namespace Tests\Feature\Api;

use App\Console\Commands\PollLiveScores;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Covers the cache-served live API endpoint (Phase 3, commit 2): GET /api/live.
 * The endpoint is read-only against the cache key written by PollLiveScores and
 * must NEVER call upstream — it serves whatever the poller last wrote, or an
 * empty/stale envelope before the poller's first run. Always HTTP 200.
 *
 * No RefreshDatabase: this endpoint only touches the cache (array store per
 * phpunit.xml), never the database.
 */
class LiveTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The endpoint must be fully cache-served: any wire request is a bug.
        Http::preventStrayRequests();
    }

    /**
     * Two minimal live match shapes as the poller would have written them.
     *
     * @return array<int, array<string, mixed>>
     */
    private function liveMatches(): array
    {
        return [
            [
                'id' => '101',
                'status' => 'LIVE',
                'minute' => 37,
                'homeTeam' => 'Arsenal',
                'awayTeam' => 'Chelsea',
                'homeScore' => 1,
                'awayScore' => 0,
                'prevHomeScore' => 0,
                'prevAwayScore' => 0,
            ],
            [
                'id' => '102',
                'status' => 'PAUSED',
                'minute' => 45,
                'homeTeam' => 'Liverpool',
                'awayTeam' => 'Everton',
                'homeScore' => 2,
                'awayScore' => 2,
                'prevHomeScore' => 1,
                'prevAwayScore' => 2,
            ],
        ];
    }

    public function test_it_serves_the_cached_live_payload(): void
    {
        $matches = $this->liveMatches();

        Cache::put(PollLiveScores::CACHE_KEY, [
            'matches' => $matches,
            'count' => 2,
            'lastUpdated' => '2026-06-08T05:00:00+00:00',
        ], 70);

        $response = $this->getJson('/api/live');

        $response->assertOk();
        $response->assertJsonPath('data.count', 2);
        $response->assertJsonPath('data.matches', $matches);
        $response->assertJsonPath('meta.lastUpdated', '2026-06-08T05:00:00+00:00');
        $response->assertJsonPath('meta.stale', false);
        $response->assertJsonPath('meta.cached', true);
    }

    public function test_it_returns_an_empty_stale_envelope_when_the_poller_has_never_run(): void
    {
        $this->assertNull(Cache::get(PollLiveScores::CACHE_KEY));

        $response = $this->getJson('/api/live');

        $response->assertOk();
        $response->assertJsonPath('data.matches', []);
        $response->assertJsonPath('data.count', 0);
        $response->assertJsonPath('meta.lastUpdated', null);
        $response->assertJsonPath('meta.stale', true);
        $response->assertJsonPath('meta.cached', true);
    }

    public function test_it_never_calls_upstream_when_cache_is_seeded(): void
    {
        Cache::put(PollLiveScores::CACHE_KEY, [
            'matches' => $this->liveMatches(),
            'count' => 2,
            'lastUpdated' => '2026-06-08T05:00:00+00:00',
        ], 70);

        $this->getJson('/api/live')->assertOk();

        Http::assertNothingSent();
    }

    public function test_it_never_calls_upstream_when_cache_is_empty(): void
    {
        $this->getJson('/api/live')->assertOk();

        Http::assertNothingSent();
    }

    public function test_it_defensively_returns_zero_count_for_a_malformed_payload(): void
    {
        // Poller-shaped key but `matches` is missing — the controller's
        // is_array guards must coerce this to an empty, non-stale envelope.
        Cache::put(PollLiveScores::CACHE_KEY, [
            'count' => 5,
            'lastUpdated' => '2026-06-08T05:00:00+00:00',
        ], 70);

        $response = $this->getJson('/api/live');

        $response->assertOk();
        $response->assertJsonPath('data.matches', []);
        $response->assertJsonPath('data.count', 0);
        // Payload is present (non-null), so it is not flagged stale.
        $response->assertJsonPath('meta.stale', false);
        $response->assertJsonPath('meta.cached', true);
        $response->assertJsonPath('meta.lastUpdated', '2026-06-08T05:00:00+00:00');

        Http::assertNothingSent();
    }

    public function test_it_ignores_a_non_string_last_updated_value(): void
    {
        // lastUpdated written as a non-string must surface as null, never leak.
        Cache::put(PollLiveScores::CACHE_KEY, [
            'matches' => $this->liveMatches(),
            'count' => 2,
            'lastUpdated' => 1717822800,
        ], 70);

        $response = $this->getJson('/api/live');

        $response->assertOk();
        $response->assertJsonPath('data.count', 2);
        $response->assertJsonPath('meta.lastUpdated', null);
        $response->assertJsonPath('meta.stale', false);
        $response->assertJsonPath('meta.cached', true);
    }
}
