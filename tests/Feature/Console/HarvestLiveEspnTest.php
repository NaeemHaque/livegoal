<?php

namespace Tests\Feature\Console;

use App\Console\Commands\HarvestLiveEspn;
use App\Console\Commands\PollLiveScores;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Covers app:harvest-live-espn — the scheduled command that fetches ESPN's live
 * status/minute/score for the in-play set the poller surfaced and writes it to a
 * side cache that GET /api/live merges. It must skip the wire entirely (and clear
 * a stale overlay) when nothing is live.
 */
class HarvestLiveEspnTest extends TestCase
{
    /**
     * @param  list<array<string, mixed>>  $matches
     */
    private function seedLive(array $matches): void
    {
        Cache::put(PollLiveScores::CACHE_KEY, [
            'matches' => $matches,
            'count' => count($matches),
            'lastUpdated' => '2026-06-14T04:30:00+00:00',
        ], 90);
    }

    /**
     * @return array<string, mixed>
     */
    private function liveMatch(): array
    {
        return [
            'id' => '777',
            'status' => 'PAUSED',
            'minute' => 45,
            'homeScore' => 0,
            'awayScore' => 0,
            'kickoff' => '2026-06-14T04:00:00+00:00',
            'competition' => ['code' => 'WC', 'name' => 'FIFA World Cup'],
            'home' => ['tla' => 'AUS', 'name' => 'Australia'],
            'away' => ['tla' => 'TUR', 'name' => 'Turkey'],
        ];
    }

    public function test_it_writes_the_espn_overlay_for_live_matches(): void
    {
        $this->seedLive([$this->liveMatch()]);
        Http::fake(['*fifa.world/scoreboard*' => Http::response($this->espnScoreboard(), 200)]);

        $this->artisan('app:harvest-live-espn')->assertSuccessful();

        $map = Cache::get(HarvestLiveEspn::OVERLAY_KEY);

        $this->assertIsArray($map);
        $this->assertSame('LIVE', $map['777']['status']);
        $this->assertSame(67, $map['777']['minute']);
        $this->assertSame(2, $map['777']['homeScore']);
    }

    public function test_it_clears_the_overlay_and_skips_the_wire_when_nothing_is_live(): void
    {
        // A stale overlay from a previous run, and no live matches now.
        Cache::put(HarvestLiveEspn::OVERLAY_KEY, ['777' => ['status' => 'LIVE']], 120);
        $this->seedLive([]);
        Http::preventStrayRequests();

        $this->artisan('app:harvest-live-espn')->assertSuccessful();

        $this->assertNull(Cache::get(HarvestLiveEspn::OVERLAY_KEY));
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
