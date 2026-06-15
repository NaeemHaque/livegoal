<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Covers GET /api/matches/{id}/espn — the POC endpoint that augments a
 * football-data match with ESPN's real clock + official event minutes. The
 * football-data match supplies the teams/date used to resolve the ESPN event.
 */
class EspnMatchTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function footballDataMatch(): array
    {
        return [
            'id' => 777,
            'utcDate' => '2026-06-14T04:00:00Z',
            'status' => 'IN_PLAY',
            'stage' => 'GROUP_STAGE',
            'group' => 'GROUP_D',
            'competition' => ['id' => 2000, 'name' => 'FIFA World Cup', 'code' => 'WC', 'type' => 'CUP'],
            'homeTeam' => ['id' => 1, 'name' => 'Australia', 'tla' => 'AUS', 'crest' => null],
            'awayTeam' => ['id' => 2, 'name' => 'Turkey', 'tla' => 'TUR', 'crest' => null],
            'score' => ['winner' => null, 'fullTime' => ['home' => 0, 'away' => 0]],
            'referees' => [],
        ];
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

    /**
     * @return array<string, mixed>
     */
    private function espnSummary(): array
    {
        return [
            'keyEvents' => [
                ['type' => ['text' => 'Goal'], 'clock' => ['displayValue' => "27'"], 'team' => ['id' => '628'], 'participants' => [['athlete' => ['displayName' => 'Irankunda']]]],
            ],
        ];
    }

    public function test_it_returns_espn_live_data_for_a_resolved_match(): void
    {
        Http::fake([
            '*api.football-data.org/v4/matches/777' => Http::response($this->footballDataMatch(), 200),
            '*fifa.world/scoreboard*' => Http::response($this->espnScoreboard(), 200),
            '*fifa.world/summary*' => Http::response($this->espnSummary(), 200),
        ]);

        $response = $this->getJson('/api/matches/777/espn');

        $response->assertOk();
        $response->assertJsonPath('data.found', true);
        $response->assertJsonPath('data.status', 'LIVE');
        $response->assertJsonPath('data.minute', 67);
        $response->assertJsonPath('data.displayClock', "67'");
        $response->assertJsonPath('data.homeScore', 2); // Australia (our home)
        $response->assertJsonPath('data.awayScore', 1); // Turkey (our away)
        $response->assertJsonPath('data.events.0.type', 'GOAL');
        $response->assertJsonPath('data.events.0.minute', 27);
        $response->assertJsonPath('data.events.0.side', 'home');
        $response->assertJsonPath('data.events.0.player', 'Irankunda');
    }

    public function test_it_returns_not_found_when_no_espn_event_matches(): void
    {
        $scoreboard = $this->espnScoreboard();
        // A different fixture — our teams aren't here, so resolution fails.
        $scoreboard['events'][0]['competitions'][0]['competitors'] = [
            ['homeAway' => 'home', 'score' => '0', 'team' => ['id' => '1', 'abbreviation' => 'BRA', 'displayName' => 'Brazil', 'shortDisplayName' => 'Brazil']],
            ['homeAway' => 'away', 'score' => '0', 'team' => ['id' => '2', 'abbreviation' => 'ARG', 'displayName' => 'Argentina', 'shortDisplayName' => 'Argentina']],
        ];

        Http::fake([
            '*api.football-data.org/v4/matches/777' => Http::response($this->footballDataMatch(), 200),
            '*fifa.world/scoreboard*' => Http::response($scoreboard, 200),
        ]);

        $response = $this->getJson('/api/matches/777/espn');

        $response->assertOk();
        $response->assertJsonPath('data.found', false);
        $response->assertJsonPath('data.events', []);
    }
}
