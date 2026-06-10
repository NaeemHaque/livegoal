<?php

namespace Tests\Feature\Seo;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Covers the server-rendered factual body the SEO shell prerenders into #app for
 * detail pages. AI answer crawlers (GPTBot/ClaudeBot/PerplexityBot) don't run
 * JavaScript, so the facts (scores, tables, fixtures) must be in the raw HTML —
 * Vue replaces this block on mount, so users still get the SPA (content parity =
 * not cloaking). Reads are cache-only; preventStrayRequests guards that.
 */
class SeoPrerenderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Http::preventStrayRequests();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function cacheUpstream(string $key, array $payload): void
    {
        Cache::put("fd:{$key}", ['data' => $payload, 'at' => '2026-06-10T12:00:00+00:00'], 600);
    }

    /** @return array<string, mixed> */
    private function scheduledMatch(): array
    {
        return [
            'id' => 1,
            'competition' => ['id' => 2021, 'name' => 'Premier League', 'code' => 'PL', 'type' => 'LEAGUE'],
            'homeTeam' => ['id' => 57, 'name' => 'Arsenal FC', 'tla' => 'ARS'],
            'awayTeam' => ['id' => 61, 'name' => 'Chelsea FC', 'tla' => 'CHE'],
            'status' => 'TIMED',
            'utcDate' => '2026-06-11T19:00:00Z',
            'venue' => 'Emirates Stadium',
            'stage' => 'REGULAR_SEASON',
            'score' => ['fullTime' => ['home' => null, 'away' => null], 'winner' => null],
        ];
    }

    /** @return array<string, mixed> */
    private function finishedMatch(): array
    {
        return [
            'id' => 2,
            'competition' => ['id' => 2021, 'name' => 'Premier League', 'code' => 'PL', 'type' => 'LEAGUE'],
            'homeTeam' => ['id' => 57, 'name' => 'Arsenal FC', 'tla' => 'ARS'],
            'awayTeam' => ['id' => 61, 'name' => 'Chelsea FC', 'tla' => 'CHE'],
            'status' => 'FINISHED',
            'utcDate' => '2026-06-09T19:00:00Z',
            'venue' => 'Emirates Stadium',
            'stage' => 'REGULAR_SEASON',
            'score' => ['fullTime' => ['home' => 2, 'away' => 1], 'winner' => 'HOME_TEAM'],
        ];
    }

    // --- Match prerender ----------------------------------------------------

    public function test_scheduled_match_prerenders_readable_facts(): void
    {
        $this->cacheUpstream('match:1', $this->scheduledMatch());

        $this->get('/match/1')
            ->assertOk()
            ->assertSee('<h1>Arsenal FC vs Chelsea FC</h1>', false)
            ->assertSee('Emirates Stadium', false)
            ->assertSee('Premier League', false)
            ->assertSee('Last updated', false);
    }

    public function test_finished_match_prerenders_full_time_score(): void
    {
        $this->cacheUpstream('match:2', $this->finishedMatch());

        $this->get('/match/2')
            ->assertOk()
            ->assertSee('Full time', false)
            ->assertSee('2–1', false);
    }

    public function test_uncached_match_has_no_prerendered_body(): void
    {
        $this->get('/match/999999')
            ->assertOk()
            ->assertSee('id="app"', false)
            ->assertDontSee('data-seo-prerender', false)
            ->assertDontSee('Last updated', false);
    }

    // --- Competition prerender ----------------------------------------------

    public function test_competition_prerenders_standings_and_scorers(): void
    {
        $this->cacheUpstream('competition:PL', [
            'id' => 2021, 'name' => 'Premier League', 'code' => 'PL', 'type' => 'LEAGUE',
        ]);
        $this->cacheUpstream('standings:PL', [
            'standings' => [[
                'type' => 'TOTAL',
                'table' => [[
                    'position' => 1,
                    'team' => ['id' => 57, 'name' => 'Arsenal FC', 'tla' => 'ARS'],
                    'playedGames' => 10, 'won' => 8, 'draw' => 1, 'lost' => 1,
                    'goalsFor' => 20, 'goalsAgainst' => 5, 'goalDifference' => 15,
                    'points' => 25, 'form' => 'WWDWW',
                ]],
            ]],
        ]);
        $this->cacheUpstream('competition:PL:scorers', [
            'scorers' => [[
                'player' => ['id' => 44, 'name' => 'Erling Haaland'],
                'team' => ['id' => 65, 'name' => 'Manchester City', 'tla' => 'MCI'],
                'goals' => 20, 'assists' => 5, 'penalties' => 3, 'playedMatches' => 12,
            ]],
        ]);

        $this->get('/competition/PL')
            ->assertOk()
            ->assertSee('<h1>Premier League</h1>', false)
            ->assertSee('Arsenal FC', false)
            ->assertSee('Erling Haaland', false)
            ->assertSee('Last updated', false);
    }

    // --- Team & player prerender --------------------------------------------

    public function test_team_prerenders_name_and_fixtures(): void
    {
        $this->cacheUpstream('team:57', [
            'id' => 57, 'name' => 'Arsenal FC', 'tla' => 'ARS',
            'area' => ['name' => 'England'], 'founded' => 1886, 'squad' => [],
        ]);
        $this->cacheUpstream('team:57:matches', [
            'matches' => [[
                'id' => 5,
                'competition' => ['id' => 2021, 'name' => 'Premier League', 'code' => 'PL', 'type' => 'LEAGUE'],
                'homeTeam' => ['id' => 57, 'name' => 'Arsenal FC', 'tla' => 'ARS'],
                'awayTeam' => ['id' => 61, 'name' => 'Chelsea FC', 'tla' => 'CHE'],
                'status' => 'TIMED', 'utcDate' => '2026-06-12T14:00:00Z',
                'score' => ['fullTime' => ['home' => null, 'away' => null], 'winner' => null],
            ]],
        ]);

        $this->get('/team/57')
            ->assertOk()
            ->assertSee('<h1>Arsenal FC</h1>', false)
            ->assertSee('Chelsea FC', false)
            ->assertSee('Last updated', false);
    }

    public function test_player_prerenders_profile_facts(): void
    {
        $this->cacheUpstream('person:44', [
            'id' => 44, 'name' => 'Erling Haaland', 'position' => 'Centre-Forward',
            'nationality' => 'Norway', 'shirtNumber' => 9,
            'currentTeam' => ['id' => 65, 'name' => 'Manchester City', 'tla' => 'MCI'],
        ]);

        $this->get('/player/44')
            ->assertOk()
            ->assertSee('<h1>Erling Haaland</h1>', false)
            ->assertSee('Centre-Forward', false)
            ->assertSee('Norway', false);
    }
}
