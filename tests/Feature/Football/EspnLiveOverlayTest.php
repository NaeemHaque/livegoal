<?php

namespace Tests\Feature\Football;

use App\Services\Football\EspnLiveOverlay;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Covers EspnLiveOverlay — the bridge that lets the home "Live now" rail keep
 * pace with the match page by layering ESPN's near-real-time status/minute/score
 * onto the football-data live feed. overlay() is a pure cache merge (no wire);
 * harvest() is the scheduled-only upstream fetch.
 */
class EspnLiveOverlayTest extends TestCase
{
    private function overlay(): EspnLiveOverlay
    {
        return app(EspnLiveOverlay::class);
    }

    /**
     * A normalized live match as the poller writes it.
     *
     * @param  array<string, mixed>  $over
     * @return array<string, mixed>
     */
    private function liveMatch(array $over = []): array
    {
        return array_merge([
            'id' => '777',
            'status' => 'PAUSED',
            'minute' => 45,
            'homeScore' => 0,
            'awayScore' => 0,
            'kickoff' => '2026-06-14T04:00:00+00:00',
            'competition' => ['code' => 'WC', 'name' => 'FIFA World Cup'],
            'home' => ['tla' => 'AUS', 'name' => 'Australia'],
            'away' => ['tla' => 'TUR', 'name' => 'Turkey'],
        ], $over);
    }

    // --- overlay(): pure merge, never touches the wire ----------------------

    public function test_overlay_applies_espn_status_minute_and_score(): void
    {
        Http::preventStrayRequests();

        $matches = [$this->liveMatch(['status' => 'PAUSED', 'minute' => 45, 'homeScore' => 0, 'awayScore' => 0])];
        $espnMap = ['777' => ['status' => 'LIVE', 'minute' => 47, 'displayClock' => "47'", 'homeScore' => 1, 'awayScore' => 0]];

        $result = $this->overlay()->overlay($matches, $espnMap);

        $this->assertSame('LIVE', $result[0]['status']);
        $this->assertSame(47, $result[0]['minute']);
        $this->assertSame("47'", $result[0]['displayClock']);
        $this->assertSame(1, $result[0]['homeScore']);
        $this->assertSame(0, $result[0]['awayScore']);

        Http::assertNothingSent();
    }

    public function test_overlay_never_downgrades_a_started_match_to_scheduled(): void
    {
        // A stale/pre-match ESPN scoreboard that still reads SCHEDULED must not
        // revert an already-started match (that would strand the UI).
        $matches = [$this->liveMatch(['status' => 'LIVE', 'minute' => 12])];
        $espnMap = ['777' => ['status' => 'SCHEDULED', 'minute' => null, 'displayClock' => null, 'homeScore' => null, 'awayScore' => null]];

        $result = $this->overlay()->overlay($matches, $espnMap);

        $this->assertSame('LIVE', $result[0]['status']);
        $this->assertSame(12, $result[0]['minute']); // ESPN minute null → keep football-data
    }

    public function test_overlay_leaves_matches_without_an_espn_entry_untouched(): void
    {
        $matches = [$this->liveMatch(['status' => 'PAUSED', 'minute' => 45])];

        $result = $this->overlay()->overlay($matches, []);

        $this->assertSame('PAUSED', $result[0]['status']);
        $this->assertSame(45, $result[0]['minute']);
    }

    // --- harvest(): resolves against ESPN (scheduled-only upstream) ----------

    public function test_harvest_resolves_live_data_from_espn(): void
    {
        Http::fake(['*fifa.world/scoreboard*' => Http::response($this->espnScoreboard(), 200)]);

        $map = $this->overlay()->harvest([$this->liveMatch()]);

        $this->assertArrayHasKey('777', $map);
        $this->assertSame('LIVE', $map['777']['status']);
        $this->assertSame(67, $map['777']['minute']);
        $this->assertSame(2, $map['777']['homeScore']); // Australia (our home)
        $this->assertSame(1, $map['777']['awayScore']); // Turkey (our away)
    }

    public function test_harvest_skips_matches_espn_cannot_resolve(): void
    {
        Http::fake(['*fifa.world/scoreboard*' => Http::response(['events' => []], 200)]);

        $map = $this->overlay()->harvest([$this->liveMatch()]);

        $this->assertSame([], $map);
    }

    public function test_harvest_skips_competitions_without_an_espn_slug(): void
    {
        // An unmapped competition resolves to no slug, so it must never hit the wire.
        Http::preventStrayRequests();

        $map = $this->overlay()->harvest([$this->liveMatch(['competition' => ['code' => 'ZZZ', 'name' => 'Unmapped']])]);

        $this->assertSame([], $map);
        Http::assertNothingSent();
    }

    /**
     * @return array<string, mixed>
     */
    private function espnScoreboard(): array
    {
        return [
            'events' => [[
                'id' => '760421',
                'competitions' => [[
                    'status' => ['displayClock' => "67'", 'type' => ['state' => 'in', 'detail' => '2nd Half']],
                    'competitors' => [
                        ['homeAway' => 'home', 'score' => '2', 'team' => ['id' => '628', 'abbreviation' => 'AUS', 'displayName' => 'Australia', 'shortDisplayName' => 'Australia']],
                        ['homeAway' => 'away', 'score' => '1', 'team' => ['id' => '465', 'abbreviation' => 'TUR', 'displayName' => 'Türkiye', 'shortDisplayName' => 'Türkiye']],
                    ],
                ]],
            ]],
        ];
    }
}
