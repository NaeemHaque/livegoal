<?php

namespace Tests\Feature\Api;

use App\Console\Commands\PollLiveScores;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * Covers the cache-served matches API endpoints (Phase 2, commit 4):
 * GET /api/matches, GET /api/matches/{id}, and GET /api/competitions/{id}/matches.
 * Asserts the standard envelope (data + meta.{lastUpdated,stale,cached}),
 * Normalizer match shape + status mapping, the default-date behavior, request
 * validation, query forwarding (uppercased competition/status, date window,
 * matchday), cache-hit behavior, and the 503 hard-miss path.
 *
 * No RefreshDatabase: these endpoints only touch the cache (array store per
 * phpunit.xml) and the HTTP client, never the database.
 */
class MatchesTest extends TestCase
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
     * One upstream /matches match, FINISHED with a HOME_TEAM win. Exercises the
     * full nested normalization (competition, home/away team, scores, referee).
     *
     * @return array<string, mixed>
     */
    private function finishedMatch(): array
    {
        return [
            'id' => 538155,
            'utcDate' => '2026-05-24T15:00:00Z',
            'status' => 'FINISHED',
            'stage' => 'REGULAR_SEASON',
            'group' => null,
            'competition' => [
                'id' => 2021,
                'name' => 'Premier League',
                'code' => 'PL',
                'type' => 'LEAGUE',
                'emblem' => 'https://crests.football-data.org/PL.png',
            ],
            'homeTeam' => ['id' => 71, 'name' => 'Sunderland AFC', 'tla' => 'SUN', 'crest' => 'https://crests.football-data.org/71.png'],
            'awayTeam' => ['id' => 61, 'name' => 'Chelsea FC', 'tla' => 'CHE', 'crest' => 'https://crests.football-data.org/61.png'],
            'score' => ['winner' => 'HOME_TEAM', 'fullTime' => ['home' => 2, 'away' => 1]],
            'venue' => null,
            'referees' => [['name' => 'Chris Kavanagh']],
        ];
    }

    /**
     * Build an upstream match with an arbitrary status and no scores (used to
     * exercise the status-mapping branches and the null-score path).
     *
     * @return array<string, mixed>
     */
    private function matchWithStatus(int $id, string $status): array
    {
        return [
            'id' => $id,
            'utcDate' => '2026-05-24T15:00:00Z',
            'status' => $status,
            'stage' => 'REGULAR_SEASON',
            'group' => null,
            'competition' => [
                'id' => 2021,
                'name' => 'Premier League',
                'code' => 'PL',
                'type' => 'LEAGUE',
                'emblem' => 'https://crests.football-data.org/PL.png',
            ],
            'homeTeam' => ['id' => 71, 'name' => 'Sunderland AFC', 'tla' => 'SUN', 'crest' => null],
            'awayTeam' => ['id' => 61, 'name' => 'Chelsea FC', 'tla' => 'CHE', 'crest' => null],
            'score' => ['winner' => null, 'fullTime' => ['home' => null, 'away' => null]],
            'venue' => null,
            'referees' => [],
        ];
    }

    // --- 1. index happy path: normalized FINISHED match ----------------------

    public function test_index_returns_normalized_matches_for_a_given_date(): void
    {
        Http::fake([
            '*/matches*' => Http::response(['matches' => [$this->finishedMatch()]], 200),
        ]);

        $response = $this->getJson('/api/matches?date=2026-05-24');

        $response->assertOk();
        $response->assertJsonPath('meta.stale', false);
        $response->assertJsonPath('meta.cached', false);
        $this->assertIsString($response->json('meta.lastUpdated'));

        // data is a JSON array of normalized matches, not the raw upstream envelope.
        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertArrayNotHasKey('matches', $data, 'data must be a list, not the raw upstream envelope');

        // FINISHED -> FT, scores + winner carried through.
        $response->assertJsonPath('data.0.id', '538155');
        $response->assertJsonPath('data.0.status', 'FT');
        $response->assertJsonPath('data.0.homeScore', 2);
        $response->assertJsonPath('data.0.awayScore', 1);
        $response->assertJsonPath('data.0.winner', 'HOME_TEAM');
        $response->assertJsonPath('data.0.stage', 'REGULAR_SEASON');
        $response->assertJsonPath('data.0.kickoff', '2026-05-24T15:00:00Z');
        $response->assertJsonPath('data.0.referee', 'Chris Kavanagh');

        // Nested competition object (normalized).
        $response->assertJsonPath('data.0.competition.code', 'PL');
        $response->assertJsonPath('data.0.competition.short', 'Premier Lg');

        // Nested home/away team objects (normalized).
        $response->assertJsonPath('data.0.home.id', '71');
        $response->assertJsonPath('data.0.home.name', 'Sunderland AFC');
        $response->assertJsonPath('data.0.home.tla', 'SUN');
        $response->assertJsonPath('data.0.away.id', '61');
        $response->assertJsonPath('data.0.away.tla', 'CHE');
    }

    // --- 2. status mapping across the upstream status set --------------------

    public function test_index_maps_upstream_statuses_to_livegoal_set(): void
    {
        Http::fake([
            '*/matches*' => Http::response(['matches' => [
                $this->matchWithStatus(1, 'IN_PLAY'),
                $this->matchWithStatus(2, 'PAUSED'),
                $this->matchWithStatus(3, 'TIMED'),
                $this->matchWithStatus(4, 'POSTPONED'),
            ]], 200),
        ]);

        $response = $this->getJson('/api/matches?date=2026-05-24');

        $response->assertOk();
        $response->assertJsonPath('data.0.status', 'LIVE');       // IN_PLAY
        $response->assertJsonPath('data.1.status', 'HT');         // PAUSED
        $response->assertJsonPath('data.2.status', 'SCHEDULED');  // TIMED
        $response->assertJsonPath('data.3.status', 'POSTPONED');  // POSTPONED

        // A SCHEDULED match has no scores yet.
        $response->assertJsonPath('data.2.homeScore', null);
        $response->assertJsonPath('data.2.awayScore', null);
    }

    // --- 3. default date: upstream window is today ---------------------------

    public function test_index_defaults_the_date_window_to_today(): void
    {
        Http::fake([
            '*/matches*' => Http::response(['matches' => []], 200),
        ]);

        $today = Date::now()->toDateString();

        $this->getJson('/api/matches')->assertOk();

        Http::assertSent(function (Request $request) use ($today): bool {
            $query = $request->data();

            return ($query['dateFrom'] ?? null) === $today
                && ($query['dateTo'] ?? null) === $today;
        });
    }

    // --- 4. validation: invalid date -> 422, no upstream call ----------------

    public function test_index_rejects_an_invalid_date_with_422_and_no_upstream_call(): void
    {
        Http::fake([
            '*/matches*' => Http::response(['matches' => []], 200),
        ]);

        $response = $this->getJson('/api/matches?date=not-a-date');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('date');

        Http::assertNothingSent();
    }

    // --- 5. query forwarding: competition + status uppercased ----------------

    public function test_index_forwards_uppercased_competition_and_status_filters(): void
    {
        Http::fake([
            '*/matches*' => Http::response(['matches' => []], 200),
        ]);

        $this->getJson('/api/matches?date=2026-05-24&competition=pl&status=live')->assertOk();

        Http::assertSent(function (Request $request): bool {
            $query = $request->data();

            return ($query['competitions'] ?? null) === 'PL'
                && ($query['status'] ?? null) === 'LIVE';
        });
    }

    // --- 5b. day view: aggregates featured competitions server-side ----------

    public function test_day_aggregates_featured_competitions_into_one_response(): void
    {
        // Shrink the featured set so the test asserts exact call counts.
        Config::set('football.featured', ['PL', 'PD']);

        Http::fake([
            '*/competitions/PL/matches*' => Http::response(['matches' => [$this->finishedMatch()]], 200),
            '*/competitions/PD/matches*' => Http::response(['matches' => []], 200),
        ]);

        $response = $this->getJson('/api/matches/day?date=2026-05-24');

        $response->assertOk();
        $response->assertJsonPath('meta.cached', true);

        // The browser gets one merged list (PL has a match on this date, PD none).
        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $response->assertJsonPath('data.0.competition.code', 'PL');

        // One full-season call per featured competition (shared cache, filtered by
        // date server-side — no per-date upstream query).
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/competitions/PL/matches'));
    }

    public function test_day_filters_the_shared_feed_to_the_requested_date(): void
    {
        Config::set('football.featured', ['PL']);

        // PL's feed has a single match on 2026-05-24.
        Http::fake([
            '*/competitions/PL/matches*' => Http::response(['matches' => [$this->finishedMatch()]], 200),
        ]);

        // A different day is a valid 200 with an empty list (the match is filtered out).
        $this->getJson('/api/matches/day?date=2026-06-09')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_day_rejects_an_invalid_date_with_422(): void
    {
        Http::fake(['*' => Http::response(['matches' => []], 200)]);

        $this->getJson('/api/matches/day?date=nope')
            ->assertStatus(422)
            ->assertJsonValidationErrors('date');

        Http::assertNothingSent();
    }

    // --- 5c. upcoming view: next scheduled fixtures across featured ----------

    public function test_upcoming_returns_soonest_scheduled_featured_fixtures(): void
    {
        Date::setTestNow('2026-06-09T00:00:00Z');
        Config::set('football.featured', ['PL', 'WC']);

        // A future World Cup fixture (included) alongside a past finished one (excluded).
        $future = $this->matchWithStatus(900001, 'TIMED');
        $future['utcDate'] = '2026-06-11T19:00:00Z';
        $future['competition'] = ['id' => 2000, 'name' => 'FIFA World Cup', 'code' => 'WC', 'type' => 'CUP'];

        Http::fake([
            '*/competitions/PL/matches*' => Http::response(['matches' => [$this->finishedMatch()]], 200),
            '*/competitions/WC/matches*' => Http::response(['matches' => [$future]], 200),
        ]);

        $response = $this->getJson('/api/matches/upcoming');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertCount(1, $data, 'only the future scheduled fixture is upcoming');
        $response->assertJsonPath('data.0.competition.code', 'WC');
        $response->assertJsonPath('data.0.status', 'SCHEDULED');
    }

    public function test_upcoming_returns_empty_list_when_nothing_is_scheduled(): void
    {
        Date::setTestNow('2026-06-09T00:00:00Z');
        Config::set('football.featured', ['PL']);

        Http::fake([
            '*/competitions/PL/matches*' => Http::response(['matches' => [$this->finishedMatch()]], 200),
        ]);

        $this->getJson('/api/matches/upcoming')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    // --- 5d. resilience: serve last-good, never block on the rate-limited API -

    public function test_upcoming_serves_last_good_without_calling_the_rate_limited_upstream(): void
    {
        Date::setTestNow('2026-06-09T00:00:00Z');
        Config::set('football.featured', ['WC']);

        $future = $this->matchWithStatus(900001, 'TIMED');
        $future['utcDate'] = '2026-06-11T19:00:00Z';
        $future['competition'] = ['id' => 2000, 'name' => 'FIFA World Cup', 'code' => 'WC', 'type' => 'CUP'];

        // Only the last-known-good copy is cached (no fresh) — the state the warmer
        // leaves once the fresh TTL lapses.
        Cache::put('fd:last:competition:WC:matches', [
            'data' => ['matches' => [$future]],
            'at' => Date::now()->toIso8601String(),
        ], 86400);

        // The upstream would 429, but the read-only homepage path must never reach it.
        Http::fake(['*' => Http::response(['error' => 'rate limited'], 429)]);

        $response = $this->getJson('/api/matches/upcoming');

        $response->assertOk();
        $response->assertJsonPath('meta.stale', true);
        $response->assertJsonPath('data.0.competition.code', 'WC');
        $this->assertCount(1, $response->json('data'));

        Http::assertNothingSent();
    }

    // --- 6. show happy path: single normalized match -------------------------

    public function test_show_returns_a_single_normalized_match(): void
    {
        Http::fake([
            '*/matches/538155' => Http::response($this->finishedMatch(), 200),
        ]);

        $response = $this->getJson('/api/matches/538155');

        $response->assertOk();
        $response->assertJsonPath('meta.stale', false);
        $response->assertJsonPath('meta.cached', false);
        $this->assertIsString($response->json('meta.lastUpdated'));

        // data is a single normalized match object (associative, not a list).
        $response->assertJsonPath('data.id', '538155');
        $response->assertJsonPath('data.status', 'FT');
        $response->assertJsonPath('data.homeScore', 2);
        $response->assertJsonPath('data.awayScore', 1);
        $response->assertJsonPath('data.winner', 'HOME_TEAM');
        $response->assertJsonPath('data.competition.code', 'PL');
        $response->assertJsonPath('data.home.tla', 'SUN');
        $response->assertJsonPath('data.away.tla', 'CHE');
        $response->assertJsonPath('data.referee', 'Chris Kavanagh');
    }

    // --- 6b. show merges the poller's self-built timeline events -------------

    public function test_show_merges_recorded_timeline_events_into_the_payload(): void
    {
        // Events recorded by PollLiveScores under the per-match cache key.
        Cache::put(PollLiveScores::eventsKey('538155'), [
            ['type' => 'KICKOFF', 'minute' => 1, 'side' => null, 'homeScore' => 0, 'awayScore' => 0, 'at' => '2026-05-24T15:00:30+00:00'],
            ['type' => 'GOAL', 'minute' => 23, 'side' => 'home', 'homeScore' => 1, 'awayScore' => 0, 'at' => '2026-05-24T15:23:30+00:00'],
        ], PollLiveScores::EVENTS_TTL);

        Http::fake([
            '*/matches/538155' => Http::response($this->finishedMatch(), 200),
        ]);

        $response = $this->getJson('/api/matches/538155');

        $response->assertOk();
        $response->assertJsonCount(2, 'data.events');
        $response->assertJsonPath('data.events.0.type', 'KICKOFF');
        $response->assertJsonPath('data.events.1.type', 'GOAL');
        $response->assertJsonPath('data.events.1.side', 'home');
        $response->assertJsonPath('data.events.1.minute', 23);
        $response->assertJsonPath('data.events.1.homeScore', 1);
        $response->assertJsonPath('data.events.1.awayScore', 0);

        // Events come from cache only — the single upstream call is the match itself.
        Http::assertSentCount(1);
    }

    public function test_show_returns_an_empty_events_list_when_none_were_recorded(): void
    {
        Http::fake([
            '*/matches/538155' => Http::response($this->finishedMatch(), 200),
        ]);

        $this->getJson('/api/matches/538155')
            ->assertOk()
            ->assertJsonPath('data.events', []);
    }

    // --- 7. competition matches: normalized list + matchday forwarded --------

    public function test_competition_matches_returns_normalized_list_and_forwards_matchday(): void
    {
        Http::fake([
            '*/competitions/PL/matches*' => Http::response(['matches' => [$this->finishedMatch()]], 200),
        ]);

        $response = $this->getJson('/api/competitions/PL/matches?matchday=38');

        $response->assertOk();
        $response->assertJsonPath('meta.cached', false);

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $response->assertJsonPath('data.0.id', '538155');
        $response->assertJsonPath('data.0.status', 'FT');
        $response->assertJsonPath('data.0.competition.code', 'PL');

        Http::assertSent(function (Request $request): bool {
            $query = $request->data();

            return str_contains($request->url(), 'api.football-data.org/v4/competitions/PL/matches')
                && (string) ($query['matchday'] ?? null) === '38';
        });
    }

    // --- 8a. cache hit: second request served from cache, one upstream call --

    public function test_index_serves_second_request_from_cache_with_one_upstream_call(): void
    {
        Http::fake([
            '*/matches*' => Http::response(['matches' => [$this->finishedMatch()]], 200),
        ]);

        $first = $this->getJson('/api/matches?date=2026-05-24');
        $second = $this->getJson('/api/matches?date=2026-05-24');

        // Only the first request reaches upstream; the second is served from cache.
        Http::assertSentCount(1);

        $first->assertOk()->assertJsonPath('meta.cached', false);
        $second->assertOk()->assertJsonPath('meta.cached', true);
        $second->assertJsonPath('meta.stale', false);

        $this->assertSame($first->json('data'), $second->json('data'));
    }

    // --- 8b. hard miss: 429 across retries+1 -> 503, null data, stale --------

    public function test_index_returns_503_on_hard_miss_with_empty_cache(): void
    {
        // Default retries=2 => 3 upstream attempts (retries + 1) before the retry
        // helper throws RequestException, which the service catches and returns null.
        // With no last-known-good cached, cached() yields data=null => envelope 503.
        Http::fake([
            '*/matches*' => Http::sequence()
                ->push(['error' => 'rate limited'], 429)
                ->push(['error' => 'rate limited'], 429)
                ->push(['error' => 'rate limited'], 429)
                ->whenEmpty(Http::response(['unexpected' => true], 500)),
        ]);

        $response = $this->getJson('/api/matches?date=2026-05-24');

        $response->assertStatus(503);
        $response->assertJsonPath('data', null);
        $response->assertJsonPath('meta.stale', true);
        $response->assertJsonPath('meta.cached', false);
        $response->assertJsonPath('meta.lastUpdated', null);

        // Three upstream attempts on the single failing call; backoff slept twice.
        Http::assertSentCount(3);
        Sleep::assertSleptTimes(2);
    }
}
