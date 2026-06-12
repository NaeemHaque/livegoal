<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * Covers the cache-served standings API endpoint (Phase 2, commit 3):
 * GET /api/competitions/{id}/standings. The acceptance focus is group parsing —
 * leagues collapse to a single overall "Table" while tournaments expand to one
 * humanized group per upstream standing. Also asserts that HOME/AWAY standings
 * are ignored (only TOTAL is used), row field mapping (numeric coercion, nested
 * team, form->array, qualification zone), the standard envelope, cache-hit
 * behavior (one upstream call), and the 503 hard-miss path.
 *
 * No RefreshDatabase: this endpoint only touches the cache (array store per
 * phpunit.xml) and the HTTP client, never the database.
 */
class StandingsTest extends TestCase
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
     * Build one upstream standings table row.
     *
     * @return array<string, mixed>
     */
    private function row(int $position, int $teamId, string $name, string $tla, string $form): array
    {
        return [
            'position' => $position,
            'team' => [
                'id' => $teamId,
                'name' => $name,
                'tla' => $tla,
                'crest' => "https://crests.football-data.org/{$teamId}.png",
            ],
            'playedGames' => 10,
            'form' => $form,
            'won' => 6,
            'draw' => 2,
            'lost' => 2,
            'points' => 20,
            'goalsFor' => 18,
            'goalsAgainst' => 9,
            'goalDifference' => 9,
        ];
    }

    /**
     * League (PL) upstream: a single REGULAR_SEASON standing with HOME/AWAY/TOTAL
     * splits. Only TOTAL should survive normalization. The TOTAL table has 6 rows
     * so the relegation-zone branch (total >= 6) is reachable for league assertions.
     *
     * @return array<string, mixed>
     */
    private function upstreamLeaguePL(): array
    {
        $table = [
            $this->row(1, 57, 'Arsenal FC', 'ARS', 'W,W,D,L,W'),
            $this->row(2, 65, 'Manchester City FC', 'MCI', 'W,W,W,D,W'),
            $this->row(3, 64, 'Liverpool FC', 'LIV', 'D,W,W,L,W'),
            $this->row(4, 61, 'Chelsea FC', 'CHE', 'L,D,W,W,D'),
            $this->row(5, 66, 'Manchester United FC', 'MUN', 'L,L,D,W,L'),
            $this->row(6, 76, 'Wolverhampton Wanderers FC', 'WOL', 'L,L,L,D,L'),
        ];

        return [
            'standings' => [
                ['stage' => 'REGULAR_SEASON', 'type' => 'HOME', 'group' => null, 'table' => $table],
                ['stage' => 'REGULAR_SEASON', 'type' => 'AWAY', 'group' => null, 'table' => $table],
                ['stage' => 'REGULAR_SEASON', 'type' => 'TOTAL', 'group' => null, 'table' => $table],
            ],
        ];
    }

    /**
     * Tournament (WC) upstream: three group standings (GROUP_A/B/C), each a TOTAL
     * table of 4. Positions 1-2 are the qualify zone; 3-4 have no zone.
     *
     * @return array<string, mixed>
     */
    private function upstreamWorldCup(): array
    {
        $groupTable = fn (string $prefix): array => [
            $this->row(1, 1000, "{$prefix} First", 'GF1', 'W,W,W'),
            $this->row(2, 1001, "{$prefix} Second", 'GS2', 'W,D,L'),
            $this->row(3, 1002, "{$prefix} Third", 'GT3', 'D,L,L'),
            $this->row(4, 1003, "{$prefix} Fourth", 'GF4', 'L,L,L'),
        ];

        return [
            'standings' => [
                ['stage' => 'GROUP_STAGE', 'type' => 'TOTAL', 'group' => 'GROUP_A', 'table' => $groupTable('A')],
                ['stage' => 'GROUP_STAGE', 'type' => 'TOTAL', 'group' => 'GROUP_B', 'table' => $groupTable('B')],
                ['stage' => 'GROUP_STAGE', 'type' => 'TOTAL', 'group' => 'GROUP_C', 'table' => $groupTable('C')],
            ],
        ];
    }

    // --- 1. league happy path: single "Table" group, rows + zones ------------

    public function test_league_standings_returns_single_table_group_with_mapped_rows(): void
    {
        Http::fake([
            '*/competitions/PL/standings' => Http::response($this->upstreamLeaguePL(), 200),
        ]);

        $response = $this->getJson('/api/competitions/PL/standings');

        $response->assertOk();

        // A league collapses to exactly one overall group.
        $groups = $response->json('data.groups');
        $this->assertIsArray($groups);
        $this->assertCount(1, $groups);

        $response->assertJsonPath('data.groups.0.key', 'TOTAL');
        $response->assertJsonPath('data.groups.0.label', 'Table');

        // First row: numeric fields, nested team, parsed form, qualify zone.
        $first = $response->json('data.groups.0.rows.0');
        $this->assertSame(1, $first['position']);
        $this->assertSame(10, $first['played']);
        $this->assertSame(6, $first['won']);
        $this->assertSame(2, $first['draw']);
        $this->assertSame(2, $first['lost']);
        $this->assertSame(18, $first['goalsFor']);
        $this->assertSame(9, $first['goalsAgainst']);
        $this->assertSame(9, $first['goalDifference']);
        $this->assertSame(20, $first['points']);

        // Nested, normalized team object (tla used for short).
        $this->assertSame('57', $first['team']['id']);
        $this->assertSame('Arsenal FC', $first['team']['name']);
        $this->assertSame('ARS', $first['team']['short']);
        $this->assertSame('ARS', $first['team']['tla']);
        $this->assertSame('https://crests.football-data.org/57.png', $first['team']['crest']);

        // "W,W,D,L,W" parses to a W/D/L array.
        $this->assertSame(['W', 'W', 'D', 'L', 'W'], $first['form']);

        // Position 1 in a 6-team league table is in the qualify zone.
        $this->assertSame('qualify', $first['zone']);
    }

    // --- 2. HOME/AWAY ignored: only the TOTAL standing becomes a group -------

    public function test_home_and_away_standings_are_ignored(): void
    {
        // The fixture carries HOME + AWAY + TOTAL; only TOTAL must survive.
        Http::fake([
            '*/competitions/PL/standings' => Http::response($this->upstreamLeaguePL(), 200),
        ]);

        $response = $this->getJson('/api/competitions/PL/standings');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.groups'));
        $response->assertJsonPath('data.groups.0.key', 'TOTAL');
    }

    // --- 3. tournament groups: one humanized group per standing -------------

    public function test_world_cup_standings_returns_one_humanized_group_per_standing(): void
    {
        Http::fake([
            '*/competitions/WC/standings' => Http::response($this->upstreamWorldCup(), 200),
        ]);

        $response = $this->getJson('/api/competitions/WC/standings');

        $response->assertOk();

        $groups = $response->json('data.groups');
        $this->assertIsArray($groups);
        $this->assertCount(3, $groups);

        // Keys preserved, labels humanized ("GROUP_A" -> "Group A").
        $this->assertSame(['GROUP_A', 'GROUP_B', 'GROUP_C'], array_column($groups, 'key'));
        $this->assertSame(['Group A', 'Group B', 'Group C'], array_column($groups, 'label'));

        // Each group keeps its own 4 rows.
        foreach ($groups as $group) {
            $this->assertCount(4, $group['rows']);
        }

        // Group-stage zones: positions 1-2 qualify, 3-4 have no zone.
        $rows = $groups[0]['rows'];
        $this->assertSame('qualify', $rows[0]['zone']);
        $this->assertSame('qualify', $rows[1]['zone']);
        $this->assertNull($rows[2]['zone']);
        $this->assertNull($rows[3]['zone']);

        // Sanity: the group's first row carries its own team.
        $this->assertSame('A First', $rows[0]['team']['name']);
        $this->assertSame(['W', 'W', 'W'], $rows[0]['form']);
    }

    // --- 4. envelope + cache hit: second request served from cache ----------

    public function test_standings_serves_second_request_from_cache_with_one_upstream_call(): void
    {
        Http::fake([
            '*/competitions/PL/standings' => Http::response($this->upstreamLeaguePL(), 200),
        ]);

        $first = $this->getJson('/api/competitions/PL/standings');
        $second = $this->getJson('/api/competitions/PL/standings');

        // Only the first request reaches upstream; the second is served from cache.
        Http::assertSentCount(1);

        // First call is a fresh upstream fill.
        $first->assertOk();
        $first->assertJsonPath('meta.stale', false);
        $first->assertJsonPath('meta.cached', false);
        $this->assertIsString($first->json('meta.lastUpdated'));

        // Second call is served from cache.
        $second->assertOk();
        $second->assertJsonPath('meta.stale', false);
        $second->assertJsonPath('meta.cached', true);

        // Same normalized payload from both calls.
        $this->assertSame($first->json('data'), $second->json('data'));
    }

    // --- 5. hard miss: 429 x (retries+1) with empty cache -> 503, null data --

    public function test_standings_returns_503_on_hard_miss_with_empty_cache(): void
    {
        // Default retries=2 => 3 upstream attempts (retries + 1) before the retry
        // helper throws RequestException, which the service catches and returns null.
        // With no last-known-good cached, cached() yields data=null => envelope 503.
        Http::fake([
            '*/competitions/PL/standings' => Http::sequence()
                ->push(['error' => 'rate limited'], 429)
                ->push(['error' => 'rate limited'], 429)
                ->push(['error' => 'rate limited'], 429)
                ->whenEmpty(Http::response(['unexpected' => true], 500)),
        ]);

        $response = $this->getJson('/api/competitions/PL/standings');

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
