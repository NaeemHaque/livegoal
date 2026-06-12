<?php

namespace Tests\Feature\Console;

use App\Console\Commands\PollLiveScores;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * Covers the site-wide live poller command (Phase 3, commit 1):
 * `php artisan app:poll-live-scores`.
 *
 * Asserts that in-play/paused matches are fetched in a single upstream call,
 * normalized (IN_PLAY -> LIVE, PAUSED -> HT, scores from score.fullTime), and
 * written to the `live:matches` cache with count + lastUpdated; that each cached
 * match gains a derived `minute` and prev-score fields from the previous poll;
 * the empty-list path; and the upstream-failure path that keeps the last good
 * cache instead of blanking it.
 *
 * No RefreshDatabase: the command only touches the cache (array store per
 * phpunit.xml) and the HTTP client, never the database.
 */
class PollLiveScoresTest extends TestCase
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

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    /**
     * One upstream /matches match with a given id, status, kickoff and scores.
     *
     * @return array<string, mixed>
     */
    private function upstreamMatch(
        int $id,
        string $status,
        string $utcDate,
        ?int $home = null,
        ?int $away = null,
    ): array {
        return [
            'id' => $id,
            'utcDate' => $utcDate,
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
            'score' => ['winner' => null, 'fullTime' => ['home' => $home, 'away' => $away]],
            'venue' => null,
            'referees' => [],
        ];
    }

    // --- 1. happy path: live matches normalized + cached ---------------------

    public function test_it_caches_normalized_live_matches_with_count_and_timestamp(): void
    {
        Http::fake([
            '*/matches*' => Http::response(['matches' => [
                $this->upstreamMatch(1, 'IN_PLAY', '2026-06-08T14:00:00Z', 1, 0),
                $this->upstreamMatch(2, 'PAUSED', '2026-06-08T14:30:00Z', 2, 2),
            ]], 200),
        ]);

        $this->artisan('app:poll-live-scores')
            ->expectsOutputToContain('Live: 2 match(es)')
            ->assertSuccessful();

        // The command queries the in-play/paused endpoint, exactly once.
        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'api.football-data.org/v4/matches')
            && ($request->data()['status'] ?? null) === 'IN_PLAY,PAUSED');
        Http::assertSentCount(1);

        $cached = Cache::get(PollLiveScores::CACHE_KEY);

        $this->assertIsArray($cached);
        $this->assertSame(2, $cached['count']);
        $this->assertIsString($cached['lastUpdated']);
        $this->assertCount(2, $cached['matches']);

        // IN_PLAY -> LIVE, scores carried from score.fullTime.
        $this->assertSame('1', $cached['matches'][0]['id']);
        $this->assertSame('LIVE', $cached['matches'][0]['status']);
        $this->assertSame(1, $cached['matches'][0]['homeScore']);
        $this->assertSame(0, $cached['matches'][0]['awayScore']);

        // PAUSED -> HT.
        $this->assertSame('2', $cached['matches'][1]['id']);
        $this->assertSame('HT', $cached['matches'][1]['status']);
        $this->assertSame(2, $cached['matches'][1]['homeScore']);
        $this->assertSame(2, $cached['matches'][1]['awayScore']);
    }

    public function test_it_writes_the_cache_value_under_the_live_key(): void
    {
        Config::set('football.ttl.live', 70);

        Http::fake([
            '*/matches*' => Http::response(['matches' => [
                $this->upstreamMatch(1, 'IN_PLAY', '2026-06-08T14:00:00Z', 0, 0),
            ]], 200),
        ]);

        Date::setTestNow('2026-06-08T15:00:00Z');

        $this->artisan('app:poll-live-scores')->assertSuccessful();

        // The value is present and re-readable under the documented key.
        $this->assertSame('live:matches', PollLiveScores::CACHE_KEY);
        $this->assertSame(1, Cache::get(PollLiveScores::CACHE_KEY)['count']);
    }

    // --- 2. minute derivation -----------------------------------------------

    public function test_it_derives_a_live_minute_per_match_status(): void
    {
        // Fix "now" so the elapsed-minute math is deterministic.
        Date::setTestNow('2026-06-08T15:00:00Z');

        Http::fake([
            '*/matches*' => Http::response(['matches' => [
                // PAUSED -> HT -> always 45.
                $this->upstreamMatch(1, 'PAUSED', '2026-06-08T14:00:00Z', 1, 1),
                // IN_PLAY, kicked off 30 minutes ago -> ~30.
                $this->upstreamMatch(2, 'IN_PLAY', '2026-06-08T14:30:00Z', 0, 0),
                // SCHEDULED (not live, not HT) -> null.
                $this->upstreamMatch(3, 'TIMED', '2026-06-08T18:00:00Z', null, null),
            ]], 200),
        ]);

        $this->artisan('app:poll-live-scores')->assertSuccessful();

        $matches = Cache::get(PollLiveScores::CACHE_KEY)['matches'];

        // HT match -> fixed 45.
        $this->assertSame('HT', $matches[0]['status']);
        $this->assertSame(45, $matches[0]['minute']);

        // LIVE match -> positive int approximating elapsed minutes (~30).
        $this->assertSame('LIVE', $matches[1]['status']);
        $this->assertIsInt($matches[1]['minute']);
        $this->assertGreaterThan(0, $matches[1]['minute']);
        $this->assertSame(30, $matches[1]['minute']);

        // Non-live, non-HT -> null.
        $this->assertSame('SCHEDULED', $matches[2]['status']);
        $this->assertNull($matches[2]['minute']);
    }

    // --- 3. score-diff / prevScore across two polls -------------------------

    public function test_it_records_previous_scores_and_reports_score_changes(): void
    {
        // Two distinct upstream responses across two polls in one test. A single
        // Http::fake() cannot be re-stubbed mid-test, so drive both with one
        // sequence (each successful 200 consumes exactly one sequence item).
        Http::fake([
            '*/matches*' => Http::sequence()
                ->push(['matches' => [$this->upstreamMatch(1, 'IN_PLAY', '2026-06-08T14:00:00Z', 1, 0)]], 200)
                ->push(['matches' => [$this->upstreamMatch(1, 'IN_PLAY', '2026-06-08T14:00:00Z', 2, 0)]], 200)
                ->whenEmpty(Http::response(['unexpected' => true], 500)),
        ]);

        // First poll: 1-0, no prior cache -> no score change reported.
        $this->artisan('app:poll-live-scores')
            ->expectsOutputToContain('Live: 1 match(es), 0 score change(s).')
            ->assertSuccessful();

        $first = Cache::get(PollLiveScores::CACHE_KEY)['matches'][0];
        $this->assertSame(1, $first['homeScore']);
        $this->assertNull($first['prevHomeScore']);
        $this->assertNull($first['prevAwayScore']);

        // Second poll: 2-0 -> prev score is the prior poll's 1-0, one change.
        $this->artisan('app:poll-live-scores')
            ->expectsOutputToContain('Live: 1 match(es), 1 score change(s).')
            ->assertSuccessful();

        $second = Cache::get(PollLiveScores::CACHE_KEY)['matches'][0];
        $this->assertSame(2, $second['homeScore']);
        $this->assertSame(0, $second['awayScore']);
        $this->assertSame(1, $second['prevHomeScore']);
        $this->assertSame(0, $second['prevAwayScore']);

        Http::assertSentCount(2);
    }

    // --- 4. no live matches -------------------------------------------------

    public function test_it_caches_an_empty_payload_when_no_matches_are_live(): void
    {
        Http::fake([
            '*/matches*' => Http::response(['matches' => []], 200),
        ]);

        $this->artisan('app:poll-live-scores')
            ->expectsOutputToContain('Live: 0 match(es)')
            ->assertSuccessful();

        $cached = Cache::get(PollLiveScores::CACHE_KEY);

        $this->assertIsArray($cached);
        $this->assertSame(0, $cached['count']);
        $this->assertSame([], $cached['matches']);
        $this->assertIsString($cached['lastUpdated']);
    }

    // --- 5. empty-answer flap guard ------------------------------------------

    /**
     * @return array<string, mixed> A cached payload holding one live match.
     */
    private function liveCachePayload(int $minute = 15, string $status = 'LIVE'): array
    {
        return [
            'matches' => [[
                'id' => '537327',
                'status' => $status,
                'homeScore' => 0,
                'awayScore' => 0,
                'minute' => $minute,
                'prevHomeScore' => null,
                'prevAwayScore' => null,
            ]],
            'count' => 1,
            'lastUpdated' => '2026-06-11T19:15:00+00:00',
        ];
    }

    public function test_it_holds_the_live_cache_through_unconfirmed_empty_answers(): void
    {
        $existing = $this->liveCachePayload();
        Cache::put(PollLiveScores::CACHE_KEY, $existing, 70);

        Http::fake([
            // Once the empty answer is confirmed, the vanished match is
            // verified against its own record: FINISHED -> really drop it.
            '*/matches/537327' => Http::response(['id' => 537327, 'status' => 'FINISHED'], 200),
            '*/matches*' => Http::response(['matches' => []], 200),
        ]);

        // Two consecutive empty answers: both held, cache untouched, no verification call.
        foreach ([1, 2] as $streak) {
            $this->artisan('app:poll-live-scores')
                ->expectsOutputToContain(sprintf('held last cache (%d/3)', $streak))
                ->assertSuccessful();

            $this->assertSame($existing, Cache::get(PollLiveScores::CACHE_KEY));
        }

        // Third consecutive empty answer: confirmed + verified FINISHED -> cleared.
        $this->artisan('app:poll-live-scores')
            ->expectsOutputToContain('Live: 0 match(es)')
            ->assertSuccessful();

        $cached = Cache::get(PollLiveScores::CACHE_KEY);
        $this->assertSame(0, $cached['count']);
        $this->assertSame([], $cached['matches']);
        $this->assertNull(Cache::get(PollLiveScores::EMPTY_STREAK_KEY));
    }

    // --- 6. vanished-match verification (free tier "erases" half-time) -------

    public function test_a_vanished_match_reported_timed_in_the_interval_window_is_held_as_half_time(): void
    {
        // The free-tier half-time lie at the interval: bulk feed empty, own
        // record back to TIMED, last seen LIVE around minute 47.
        Cache::put(PollLiveScores::CACHE_KEY, $this->liveCachePayload(47), 70);
        Cache::put(PollLiveScores::EMPTY_STREAK_KEY, 2, 300);

        Http::fake([
            '*/matches/537327' => Http::response(['id' => 537327, 'status' => 'TIMED'], 200),
            '*/matches*' => Http::response(['matches' => []], 200),
        ]);

        $this->artisan('app:poll-live-scores')
            ->expectsOutputToContain('Live: 1 match(es)')
            ->assertSuccessful();

        $held = Cache::get(PollLiveScores::CACHE_KEY)['matches'][0];

        $this->assertSame('HT', $held['status']);
        $this->assertSame(45, $held['minute']);
        // Last real score survives the upstream erasing it.
        $this->assertSame(0, $held['homeScore']);
        $this->assertSame(0, $held['awayScore']);
    }

    public function test_a_vanished_match_reported_timed_deep_in_the_second_half_stays_live(): void
    {
        // Same lie during the second half (last seen LIVE at 81') — the match
        // must NOT regress to HT; it stays live with the clock ticking.
        Cache::put(PollLiveScores::CACHE_KEY, $this->liveCachePayload(81), 70);
        Cache::put(PollLiveScores::EMPTY_STREAK_KEY, 2, 300);

        Http::fake([
            '*/matches/537327' => Http::response(['id' => 537327, 'status' => 'TIMED'], 200),
            '*/matches*' => Http::response(['matches' => []], 200),
        ]);

        $this->artisan('app:poll-live-scores')->assertSuccessful();

        $kept = Cache::get(PollLiveScores::CACHE_KEY)['matches'][0];

        $this->assertSame('LIVE', $kept['status']);
        $this->assertSame(81, $kept['minute']);
    }

    public function test_a_held_half_time_match_stays_at_half_time_while_the_record_lies_timed(): void
    {
        Cache::put(PollLiveScores::CACHE_KEY, $this->liveCachePayload(45, 'HT'), 70);
        Cache::put(PollLiveScores::EMPTY_STREAK_KEY, 2, 300);

        Http::fake([
            '*/matches/537327' => Http::response(['id' => 537327, 'status' => 'TIMED'], 200),
            '*/matches*' => Http::response(['matches' => []], 200),
        ]);

        $this->artisan('app:poll-live-scores')->assertSuccessful();

        $this->assertSame('HT', Cache::get(PollLiveScores::CACHE_KEY)['matches'][0]['status']);
    }

    public function test_a_vanished_match_reported_paused_is_held_as_half_time(): void
    {
        Cache::put(PollLiveScores::CACHE_KEY, $this->liveCachePayload(), 70);
        Cache::put(PollLiveScores::EMPTY_STREAK_KEY, 2, 300);

        Http::fake([
            '*/matches/537327' => Http::response(['id' => 537327, 'status' => 'PAUSED'], 200),
            '*/matches*' => Http::response(['matches' => []], 200),
        ]);

        $this->artisan('app:poll-live-scores')->assertSuccessful();

        $this->assertSame('HT', Cache::get(PollLiveScores::CACHE_KEY)['matches'][0]['status']);
    }

    public function test_a_vanished_match_still_in_play_per_its_own_record_keeps_its_fresh_score(): void
    {
        // Bulk-feed flap: the answer is non-empty but missing one live match.
        // The single record is fresher than the cache (a goal happened during
        // the gap) — its score must be carried over, not the stale 0-0.
        Cache::put(PollLiveScores::CACHE_KEY, $this->liveCachePayload(), 70);

        Http::fake([
            '*/matches/537327' => Http::response(
                $this->upstreamMatch(537327, 'IN_PLAY', '2026-06-11T19:00:00Z', 2, 0),
                200,
            ),
            '*/matches*' => Http::response(['matches' => [
                $this->upstreamMatch(9, 'IN_PLAY', '2026-06-11T19:00:00Z', 0, 0),
            ]], 200),
        ]);

        $this->artisan('app:poll-live-scores')
            ->expectsOutputToContain('Live: 2 match(es)')
            ->assertSuccessful();

        $matches = Cache::get(PollLiveScores::CACHE_KEY)['matches'];
        $byId = [];

        foreach ($matches as $m) {
            $byId[$m['id']] = $m;
        }

        $this->assertSame('LIVE', $byId['537327']['status'] ?? null);
        $this->assertSame(2, $byId['537327']['homeScore'] ?? null);
        $this->assertSame(0, $byId['537327']['awayScore'] ?? null);
        // Prev scores come from the cached entry, so goal detection can fire.
        $this->assertSame(0, $byId['537327']['prevHomeScore'] ?? null);
        $this->assertSame('LIVE', $byId['9']['status'] ?? null);
    }

    public function test_an_unconfirmed_score_drop_is_held_at_the_previous_score(): void
    {
        // A stale upstream node answers 0-0 after the cache saw 2-0: the drop
        // is held back twice, then accepted once answered consistently.
        Http::fake([
            '*/matches*' => Http::sequence()
                ->push(['matches' => [$this->upstreamMatch(1, 'IN_PLAY', '2026-06-11T19:00:00Z', 2, 0)]], 200)
                ->push(['matches' => [$this->upstreamMatch(1, 'IN_PLAY', '2026-06-11T19:00:00Z', 0, 0)]], 200)
                ->push(['matches' => [$this->upstreamMatch(1, 'IN_PLAY', '2026-06-11T19:00:00Z', 0, 0)]], 200)
                ->push(['matches' => [$this->upstreamMatch(1, 'IN_PLAY', '2026-06-11T19:00:00Z', 0, 0)]], 200)
                ->whenEmpty(Http::response(['unexpected' => true], 500)),
        ]);

        $this->artisan('app:poll-live-scores')->assertSuccessful();
        $this->assertSame(2, Cache::get(PollLiveScores::CACHE_KEY)['matches'][0]['homeScore']);

        // Drops 1 and 2: held at the last credible score.
        foreach ([1, 2] as $attempt) {
            $this->artisan('app:poll-live-scores')->assertSuccessful();
            $this->assertSame(2, Cache::get(PollLiveScores::CACHE_KEY)['matches'][0]['homeScore'], "drop attempt {$attempt}");
        }

        // Third consistent answer: the drop is real (e.g. VAR) — accept it.
        $this->artisan('app:poll-live-scores')->assertSuccessful();
        $this->assertSame(0, Cache::get(PollLiveScores::CACHE_KEY)['matches'][0]['homeScore']);
    }

    public function test_score_oscillation_does_not_record_duplicate_goal_events(): void
    {
        // 1-0 -> (stale) 0-0 held -> 2-0: exactly one extra GOAL at 2-0.
        Http::fake([
            '*/matches*' => Http::sequence()
                ->push(['matches' => [$this->upstreamMatch(1, 'IN_PLAY', '2026-06-11T19:00:00Z', 1, 0)]], 200)
                ->push(['matches' => [$this->upstreamMatch(1, 'IN_PLAY', '2026-06-11T19:00:00Z', 2, 0)]], 200)
                ->push(['matches' => [$this->upstreamMatch(1, 'IN_PLAY', '2026-06-11T19:00:00Z', 1, 0)]], 200)
                ->push(['matches' => [$this->upstreamMatch(1, 'IN_PLAY', '2026-06-11T19:00:00Z', 2, 0)]], 200)
                ->whenEmpty(Http::response(['unexpected' => true], 500)),
        ]);

        foreach (range(1, 4) as $i) {
            $this->artisan('app:poll-live-scores')->assertSuccessful();
        }

        $goals = array_filter(
            Cache::get(PollLiveScores::eventsKey('1')),
            fn (array $e): bool => $e['type'] === 'GOAL',
        );

        // One goal to 2-0 — the oscillating re-announcements are deduped.
        $this->assertCount(1, $goals);
    }

    public function test_an_erased_match_past_any_plausible_final_whistle_is_presumed_finished(): void
    {
        // Anchored clock: resumed 20:00, now 21:10 -> 45 + 70 = 115' >= 105.
        Date::setTestNow('2026-06-11T21:10:00Z');
        Cache::put(PollLiveScores::eventsKey('537327'), [
            ['type' => 'KICKOFF', 'minute' => 1, 'side' => null, 'homeScore' => 0, 'awayScore' => 0, 'at' => '2026-06-11T19:12:00+00:00'],
            ['type' => 'HT', 'minute' => 45, 'side' => null, 'homeScore' => 0, 'awayScore' => 0, 'at' => '2026-06-11T19:58:00+00:00'],
            ['type' => 'RESUME', 'minute' => 46, 'side' => null, 'homeScore' => 0, 'awayScore' => 0, 'at' => '2026-06-11T20:00:00+00:00'],
        ], 3600);
        Cache::put(PollLiveScores::CACHE_KEY, $this->liveCachePayload(98), 70);
        Cache::put(PollLiveScores::EMPTY_STREAK_KEY, 2, 300);

        Http::fake([
            '*/matches/537327' => Http::response(['id' => 537327, 'status' => 'TIMED'], 200),
            '*/matches*' => Http::response(['matches' => []], 200),
        ]);

        $this->artisan('app:poll-live-scores')
            ->expectsOutputToContain('Live: 0 match(es)')
            ->assertSuccessful();

        // Dropped from live; recorded as a final with its last known score.
        $this->assertSame([], Cache::get(PollLiveScores::CACHE_KEY)['matches']);

        $finals = Cache::get(PollLiveScores::FINALS_KEY);
        $this->assertSame('FT', $finals['537327']['status']);
        $this->assertSame(0, $finals['537327']['homeScore']);
        $this->assertNull($finals['537327']['minute']);

        $types = array_column(Cache::get(PollLiveScores::eventsKey('537327')), 'type');
        $this->assertContains('FT', $types);
    }

    public function test_a_mid_second_half_erased_match_is_not_presumed_finished(): void
    {
        // Resumed 10 minutes ago -> anchored 55' — far from any final whistle.
        Date::setTestNow('2026-06-11T20:10:00Z');
        Cache::put(PollLiveScores::eventsKey('537327'), [
            ['type' => 'RESUME', 'minute' => 46, 'side' => null, 'homeScore' => 0, 'awayScore' => 0, 'at' => '2026-06-11T20:00:00+00:00'],
        ], 3600);
        Cache::put(PollLiveScores::CACHE_KEY, $this->liveCachePayload(80), 70);
        Cache::put(PollLiveScores::EMPTY_STREAK_KEY, 2, 300);

        Http::fake([
            '*/matches/537327' => Http::response(['id' => 537327, 'status' => 'TIMED'], 200),
            '*/matches*' => Http::response(['matches' => []], 200),
        ]);

        $this->artisan('app:poll-live-scores')->assertSuccessful();

        $kept = Cache::get(PollLiveScores::CACHE_KEY)['matches'][0];
        $this->assertSame('LIVE', $kept['status']);
        $this->assertNull(Cache::get(PollLiveScores::FINALS_KEY));
    }

    public function test_a_genuinely_finished_match_is_recorded_as_a_final_with_the_authoritative_score(): void
    {
        Cache::put(PollLiveScores::CACHE_KEY, $this->liveCachePayload(98), 70);
        Cache::put(PollLiveScores::EMPTY_STREAK_KEY, 2, 300);

        Http::fake([
            '*/matches/537327' => Http::response([
                'id' => 537327,
                'status' => 'FINISHED',
                'score' => ['winner' => 'HOME_TEAM', 'fullTime' => ['home' => 2, 'away' => 0]],
            ], 200),
            '*/matches*' => Http::response(['matches' => []], 200),
        ]);

        $this->artisan('app:poll-live-scores')
            ->expectsOutputToContain('Live: 0 match(es)')
            ->assertSuccessful();

        $finals = Cache::get(PollLiveScores::FINALS_KEY);
        $this->assertSame('FT', $finals['537327']['status']);
        // The FINISHED record's full-time score wins over the cached one.
        $this->assertSame(2, $finals['537327']['homeScore']);
        $this->assertSame(0, $finals['537327']['awayScore']);

        $ft = collect(Cache::get(PollLiveScores::eventsKey('537327')))->firstWhere('type', 'FT');
        $this->assertSame(2, $ft['homeScore']);
    }

    public function test_an_ht_match_past_the_statutory_interval_is_presumed_back_live(): void
    {
        // HT marker 20 minutes old — the second half restarted ~5 minutes ago
        // in reality, however much the feed lags. Presumed restart = HT + 15m.
        Date::setTestNow('2026-06-12T03:15:00Z');
        Cache::put(PollLiveScores::eventsKey('537328'), [
            ['type' => 'KICKOFF', 'minute' => 1, 'side' => null, 'homeScore' => 0, 'awayScore' => 0, 'at' => '2026-06-12T02:06:00+00:00'],
            ['type' => 'HT', 'minute' => 45, 'side' => null, 'homeScore' => 0, 'awayScore' => 0, 'at' => '2026-06-12T02:55:00+00:00'],
        ], 3600);

        Http::fake([
            '*/matches*' => Http::response(['matches' => [
                $this->upstreamMatch(537328, 'PAUSED', '2026-06-12T02:00:00Z', 0, 0),
            ]], 200),
        ]);

        $this->artisan('app:poll-live-scores')->assertSuccessful();

        $m = Cache::get(PollLiveScores::CACHE_KEY)['matches'][0];

        // Live again, clock anchored at HT(02:55) + 15m = 03:10 -> 45 + 5 = 50'.
        $this->assertSame('LIVE', $m['status']);
        $this->assertSame(50, $m['minute']);

        $resume = collect(Cache::get(PollLiveScores::eventsKey('537328')))->firstWhere('type', 'RESUME');
        $this->assertSame('2026-06-12T03:10:00+00:00', $resume['at']);
    }

    public function test_an_ht_match_within_the_interval_stays_at_half_time(): void
    {
        // HT marker only 8 minutes old — genuinely still the interval.
        Date::setTestNow('2026-06-12T03:03:00Z');
        Cache::put(PollLiveScores::eventsKey('537328'), [
            ['type' => 'HT', 'minute' => 45, 'side' => null, 'homeScore' => 0, 'awayScore' => 0, 'at' => '2026-06-12T02:55:00+00:00'],
        ], 3600);

        Http::fake([
            '*/matches*' => Http::response(['matches' => [
                $this->upstreamMatch(537328, 'PAUSED', '2026-06-12T02:00:00Z', 0, 0),
            ]], 200),
        ]);

        $this->artisan('app:poll-live-scores')->assertSuccessful();

        $this->assertSame('HT', Cache::get(PollLiveScores::CACHE_KEY)['matches'][0]['status']);
    }

    // --- 7. anchored live minute ----------------------------------------------

    public function test_the_live_minute_is_anchored_to_the_observed_second_half_restart(): void
    {
        // Anchors observed by earlier polls: kicked off 14:02, resumed 15:02.
        Date::setTestNow('2026-06-11T15:12:00Z');
        Cache::put(PollLiveScores::eventsKey('1'), [
            ['type' => 'KICKOFF', 'minute' => 1, 'side' => null, 'homeScore' => 0, 'awayScore' => 0, 'at' => '2026-06-11T14:02:00+00:00'],
            ['type' => 'HT', 'minute' => 45, 'side' => null, 'homeScore' => 0, 'awayScore' => 0, 'at' => '2026-06-11T14:49:00+00:00'],
            ['type' => 'RESUME', 'minute' => 46, 'side' => null, 'homeScore' => 0, 'awayScore' => 0, 'at' => '2026-06-11T15:02:00+00:00'],
        ], 3600);

        Http::fake([
            '*/matches*' => Http::response(['matches' => [
                // Scheduled kickoff 13:40 — schedule math would say 92'; the
                // observed-restart anchor says 45 + 10 = 55'.
                $this->upstreamMatch(1, 'IN_PLAY', '2026-06-11T13:40:00Z', 1, 0),
            ]], 200),
        ]);

        $this->artisan('app:poll-live-scores')->assertSuccessful();

        $this->assertSame(55, Cache::get(PollLiveScores::CACHE_KEY)['matches'][0]['minute']);
    }

    public function test_a_resume_event_is_recorded_when_a_held_half_time_match_goes_live_again(): void
    {
        Cache::put(PollLiveScores::CACHE_KEY, $this->liveCachePayload(45, 'HT'), 70);
        Cache::put(PollLiveScores::eventsKey('537327'), [
            ['type' => 'KICKOFF', 'minute' => 1, 'side' => null, 'homeScore' => 0, 'awayScore' => 0, 'at' => '2026-06-11T19:12:00+00:00'],
            ['type' => 'HT', 'minute' => 45, 'side' => null, 'homeScore' => 0, 'awayScore' => 0, 'at' => '2026-06-11T19:58:00+00:00'],
        ], 3600);

        Http::fake([
            '*/matches*' => Http::response(['matches' => [
                $this->upstreamMatch(537327, 'IN_PLAY', '2026-06-11T19:00:00Z', 1, 0),
            ]], 200),
        ]);

        $this->artisan('app:poll-live-scores')->assertSuccessful();

        $types = array_column(Cache::get(PollLiveScores::eventsKey('537327')), 'type');
        $this->assertContains('RESUME', $types);
    }

    public function test_a_vanished_match_is_kept_when_verification_fails(): void
    {
        Cache::put(PollLiveScores::CACHE_KEY, $this->liveCachePayload(), 70);
        Cache::put(PollLiveScores::EMPTY_STREAK_KEY, 2, 300);

        Http::fake([
            // Verification 429s through all retries -> null -> keep last state.
            '*/matches/537327' => Http::response(['error' => 'rate limited'], 429),
            '*/matches*' => Http::response(['matches' => []], 200),
        ]);

        $this->artisan('app:poll-live-scores')->assertSuccessful();

        $kept = Cache::get(PollLiveScores::CACHE_KEY)['matches'][0];
        $this->assertSame('LIVE', $kept['status']);
        $this->assertSame('537327', $kept['id']);
    }

    public function test_a_non_empty_poll_resets_the_empty_streak(): void
    {
        Cache::put(PollLiveScores::CACHE_KEY, $this->liveCachePayload(), 70);

        Http::fake([
            '*/matches*' => Http::sequence()
                ->push(['matches' => []], 200)
                ->push(['matches' => [$this->upstreamMatch(537327, 'IN_PLAY', '2026-06-11T19:00:00Z', 0, 0)]], 200)
                ->push(['matches' => []], 200)
                ->whenEmpty(Http::response(['unexpected' => true], 500)),
        ]);

        // Empty (held, streak 1) -> live again (streak reset) -> empty (held, streak 1).
        $this->artisan('app:poll-live-scores')
            ->expectsOutputToContain('held last cache (1/3)')
            ->assertSuccessful();

        $this->artisan('app:poll-live-scores')
            ->expectsOutputToContain('Live: 1 match(es)')
            ->assertSuccessful();

        $this->artisan('app:poll-live-scores')
            ->expectsOutputToContain('held last cache (1/3)')
            ->assertSuccessful();

        $this->assertSame(1, Cache::get(PollLiveScores::CACHE_KEY)['count']);
    }

    public function test_an_empty_answer_with_no_live_cache_is_written_immediately(): void
    {
        // Quiet site: prior cache is already empty; no holding, no streak.
        Cache::put(PollLiveScores::CACHE_KEY, [
            'matches' => [],
            'count' => 0,
            'lastUpdated' => '2026-06-11T18:00:00+00:00',
        ], 70);

        Http::fake(['*/matches*' => Http::response(['matches' => []], 200)]);

        $this->artisan('app:poll-live-scores')
            ->expectsOutputToContain('Live: 0 match(es)')
            ->assertSuccessful();

        $this->assertNull(Cache::get(PollLiveScores::EMPTY_STREAK_KEY));
    }

    // --- 6. self-built timeline events ---------------------------------------

    public function test_it_records_kickoff_goal_and_half_time_timeline_events(): void
    {
        Date::setTestNow('2026-06-08T14:46:00Z');

        // The test clock jumps 46 minutes between polls; keep the live cache
        // alive across that jump so the score diff has its baseline.
        Config::set('football.ttl.live', 7200);

        $this->assertSame('live:events:1', PollLiveScores::eventsKey('1'));

        Http::fake([
            '*/matches*' => Http::sequence()
                ->push(['matches' => [$this->upstreamMatch(1, 'IN_PLAY', '2026-06-08T14:00:00Z', 0, 0)]], 200)
                ->push(['matches' => [$this->upstreamMatch(1, 'IN_PLAY', '2026-06-08T14:00:00Z', 1, 0)]], 200)
                ->push(['matches' => [$this->upstreamMatch(1, 'PAUSED', '2026-06-08T14:00:00Z', 1, 0)]], 200)
                ->whenEmpty(Http::response(['unexpected' => true], 500)),
        ]);

        // First poll: first time seen LIVE -> a single KICKOFF event.
        $this->artisan('app:poll-live-scores')->assertSuccessful();

        $events = Cache::get(PollLiveScores::eventsKey('1'));
        $this->assertIsArray($events);
        $this->assertCount(1, $events);
        $this->assertSame('KICKOFF', $events[0]['type']);
        $this->assertSame('2026-06-08T14:46:00+00:00', $events[0]['at']);

        // Second poll, 46 minutes later: home score 0 -> 1 -> a home GOAL with
        // the running score; its minute is measured from the observed kickoff.
        Date::setTestNow('2026-06-08T15:32:00Z');
        $this->artisan('app:poll-live-scores')->assertSuccessful();

        $events = Cache::get(PollLiveScores::eventsKey('1'));
        $this->assertCount(2, $events);
        $this->assertSame('GOAL', $events[1]['type']);
        $this->assertSame('home', $events[1]['side']);
        $this->assertSame(46, $events[1]['minute']);
        $this->assertSame(1, $events[1]['homeScore']);
        $this->assertSame(0, $events[1]['awayScore']);

        // Third poll: first time seen PAUSED -> an HT event with the HT score.
        $this->artisan('app:poll-live-scores')->assertSuccessful();

        $events = Cache::get(PollLiveScores::eventsKey('1'));
        $this->assertCount(3, $events);
        $this->assertSame('HT', $events[2]['type']);
        $this->assertSame(45, $events[2]['minute']);
        $this->assertSame(1, $events[2]['homeScore']);
        $this->assertSame(0, $events[2]['awayScore']);
    }

    public function test_it_records_one_goal_event_per_side_that_scored(): void
    {
        Http::fake([
            '*/matches*' => Http::sequence()
                ->push(['matches' => [$this->upstreamMatch(1, 'IN_PLAY', '2026-06-08T14:00:00Z', 0, 0)]], 200)
                // Both sides score between polls (two goals in one cadence).
                ->push(['matches' => [$this->upstreamMatch(1, 'IN_PLAY', '2026-06-08T14:00:00Z', 1, 1)]], 200)
                ->whenEmpty(Http::response(['unexpected' => true], 500)),
        ]);

        $this->artisan('app:poll-live-scores')->assertSuccessful();
        $this->artisan('app:poll-live-scores')->assertSuccessful();

        $events = Cache::get(PollLiveScores::eventsKey('1'));

        $this->assertIsArray($events);
        $this->assertSame(['KICKOFF', 'GOAL', 'GOAL'], array_column($events, 'type'));
        $this->assertSame('home', $events[1]['side']);
        $this->assertSame('away', $events[2]['side']);
    }

    public function test_it_records_kickoff_and_half_time_only_once(): void
    {
        Http::fake([
            '*/matches*' => Http::response(['matches' => [
                $this->upstreamMatch(1, 'PAUSED', '2026-06-08T14:00:00Z', 1, 1),
            ]], 200),
        ]);

        // Two polls both seeing the match paused at half-time.
        $this->artisan('app:poll-live-scores')->assertSuccessful();
        $this->artisan('app:poll-live-scores')->assertSuccessful();

        $events = Cache::get(PollLiveScores::eventsKey('1'));

        // One HT event only — and no KICKOFF, since it was never seen LIVE.
        $this->assertIsArray($events);
        $this->assertCount(1, $events);
        $this->assertSame('HT', $events[0]['type']);
    }

    public function test_it_records_no_events_for_matches_that_are_not_live(): void
    {
        Http::fake([
            '*/matches*' => Http::response(['matches' => [
                $this->upstreamMatch(3, 'TIMED', '2026-06-08T18:00:00Z', null, null),
            ]], 200),
        ]);

        $this->artisan('app:poll-live-scores')->assertSuccessful();

        $this->assertNull(Cache::get(PollLiveScores::eventsKey('3')));
    }

    // --- 7. upstream failure: keep last good cache --------------------------

    public function test_it_keeps_the_last_good_cache_when_upstream_fails(): void
    {
        // Pre-seed a known-good payload as if a previous poll succeeded.
        $existing = [
            'matches' => [[
                'id' => '99',
                'status' => 'LIVE',
                'homeScore' => 3,
                'awayScore' => 1,
                'minute' => 70,
                'prevHomeScore' => 2,
                'prevAwayScore' => 1,
            ]],
            'count' => 1,
            'lastUpdated' => '2026-06-08T14:59:00+00:00',
        ];
        Cache::put(PollLiveScores::CACHE_KEY, $existing, 70);

        // Default retries=2 => 3 upstream attempts (retries + 1) before the retry
        // helper throws RequestException, which the service catches and returns
        // null. A null upstream result must NOT overwrite the cache.
        Http::fake([
            '*/matches*' => Http::sequence()
                ->push(['error' => 'rate limited'], 429)
                ->push(['error' => 'rate limited'], 429)
                ->push(['error' => 'rate limited'], 429)
                ->whenEmpty(Http::response(['unexpected' => true], 500)),
        ]);

        Log::spy();

        $this->artisan('app:poll-live-scores')
            ->expectsOutputToContain('Upstream unavailable; kept last live cache.')
            ->assertSuccessful();

        // Three upstream attempts on the single failing call; backoff slept twice.
        Http::assertSentCount(3);
        Sleep::assertSleptTimes(2);

        // A warning was logged about the unavailable upstream.
        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message): bool => str_contains($message, 'upstream unavailable'))
            ->once();

        // The pre-existing cache is untouched, byte for byte.
        $this->assertSame($existing, Cache::get(PollLiveScores::CACHE_KEY));
    }
}
