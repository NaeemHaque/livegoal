<?php

namespace Tests\Feature\Api;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * Covers the cache-served teams / team-matches / scorers / person API endpoints
 * (Phase 2, commit 5):
 *  - GET /api/competitions/{id}/teams      -> CompetitionController@teams
 *  - GET /api/competitions/{id}/scorers    -> CompetitionController@scorers
 *  - GET /api/teams/{id}                    -> TeamController@show
 *  - GET /api/teams/{id}/matches           -> TeamController@matches
 *  - GET /api/persons/{id}                  -> PersonController@show
 *
 * Asserts the standard envelope (data + meta.{lastUpdated,stale,cached}), the
 * Normalizer shapes (team list, team detail + squad, scorers ranking, person),
 * request validation (scorers limit), query forwarding (uppercased status,
 * limit), cache-hit behavior, and the 503 hard-miss path.
 *
 * No RefreshDatabase: these endpoints only touch the cache (array store per
 * phpunit.xml) and the HTTP client, never the database.
 */
class TeamsScorersTest extends TestCase
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
     * One upstream /competitions/{id}/teams team entry.
     *
     * @return array<string, mixed>
     */
    private function competitionTeam(int $id, string $name, string $tla): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'shortName' => $name,
            'tla' => $tla,
            'crest' => "https://crests.football-data.org/{$id}.png",
            'address' => 'Somewhere',
        ];
    }

    /**
     * One upstream /competitions/{id}/scorers entry.
     *
     * @return array<string, mixed>
     */
    private function scorerEntry(int $playerId, string $playerName, string $nationality, int $goals, ?int $assists, ?int $penalties, int $playedMatches): array
    {
        return [
            'player' => [
                'id' => $playerId,
                'name' => $playerName,
                'firstName' => 'First',
                'lastName' => 'Last',
                'dateOfBirth' => '1997-02-05',
                'nationality' => $nationality,
                'section' => 'Offence',
            ],
            'team' => [
                'id' => 65,
                'name' => 'Manchester City FC',
                'shortName' => 'Man City',
                'tla' => 'MCI',
                'crest' => 'https://crests.football-data.org/65.png',
            ],
            'playedMatches' => $playedMatches,
            'goals' => $goals,
            'assists' => $assists,
            'penalties' => $penalties,
        ];
    }

    /**
     * Upstream /teams/{id} detail payload with a one-member squad.
     *
     * @return array<string, mixed>
     */
    private function teamDetail(): array
    {
        return [
            'id' => 65,
            'name' => 'Manchester City FC',
            'shortName' => 'Man City',
            'tla' => 'MCI',
            'crest' => 'https://crests.football-data.org/65.png',
            'address' => 'SportCity Manchester M11 3FF',
            'website' => 'https://www.mancity.com',
            'founded' => 1880,
            'clubColors' => 'Sky Blue / White',
            'venue' => 'Etihad Stadium',
            'area' => [
                'id' => 2072,
                'name' => 'England',
                'flag' => 'https://crests.football-data.org/770.svg',
            ],
            'squad' => [
                [
                    'id' => 3754,
                    'name' => 'Ederson',
                    'position' => 'Goalkeeper',
                    'dateOfBirth' => '1993-08-17',
                    'nationality' => 'Brazil',
                    'shirtNumber' => 31,
                ],
            ],
        ];
    }

    /**
     * Upstream /persons/{id} payload with a currentTeam.
     *
     * @return array<string, mixed>
     */
    private function personDetail(): array
    {
        return [
            'id' => 44,
            'name' => 'Cristiano Ronaldo',
            'firstName' => 'Cristiano',
            'lastName' => 'Ronaldo',
            'dateOfBirth' => '1985-02-05',
            'nationality' => 'Portugal',
            'position' => 'Offence',
            'shirtNumber' => 7,
            'currentTeam' => [
                'id' => 5530,
                'name' => 'Al-Nassr FC',
                'shortName' => 'Al-Nassr',
                'tla' => 'NAS',
                'crest' => 'https://crests.football-data.org/5530.png',
            ],
        ];
    }

    /**
     * Upstream /teams/{id}/matches payload (single FINISHED match).
     *
     * @return array<string, mixed>
     */
    private function teamMatch(): array
    {
        return [
            'matches' => [
                [
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
                    'homeTeam' => ['id' => 65, 'name' => 'Manchester City FC', 'tla' => 'MCI', 'crest' => null],
                    'awayTeam' => ['id' => 61, 'name' => 'Chelsea FC', 'tla' => 'CHE', 'crest' => null],
                    'score' => ['winner' => 'HOME_TEAM', 'fullTime' => ['home' => 2, 'away' => 1]],
                    'venue' => 'Etihad Stadium',
                    'referees' => [['name' => 'Chris Kavanagh']],
                ],
            ],
        ];
    }

    // --- 1. teams list: normalized team array --------------------------------

    public function test_competition_teams_returns_a_normalized_team_list(): void
    {
        Http::fake([
            '*/competitions/PL/teams*' => Http::response(['teams' => [
                $this->competitionTeam(65, 'Manchester City FC', 'MCI'),
                $this->competitionTeam(61, 'Chelsea FC', 'CHE'),
            ]], 200),
        ]);

        $response = $this->getJson('/api/competitions/PL/teams');

        $response->assertOk();
        $response->assertJsonPath('meta.stale', false);
        $response->assertJsonPath('meta.cached', false);
        $this->assertIsString($response->json('meta.lastUpdated'));

        // data is a JSON array of normalized teams, not the raw upstream envelope.
        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertCount(2, $data);
        $this->assertArrayNotHasKey('teams', $data, 'data must be a list, not the raw upstream envelope');

        // short is derived from tla; crest passes through unchanged.
        $response->assertJsonPath('data.0.id', '65');
        $response->assertJsonPath('data.0.name', 'Manchester City FC');
        $response->assertJsonPath('data.0.short', 'MCI');
        $response->assertJsonPath('data.0.tla', 'MCI');
        $response->assertJsonPath('data.0.crest', 'https://crests.football-data.org/65.png');

        // Normalized teams expose only the five mapped keys (no upstream extras).
        $this->assertSame(
            ['id', 'name', 'short', 'tla', 'crest'],
            array_keys($data[0]),
        );
    }

    // --- 2. scorers: ranked list + nested player/team + limit forwarded ------

    public function test_competition_scorers_returns_a_ranked_list_and_forwards_limit(): void
    {
        Http::fake([
            '*/competitions/PL/scorers*' => Http::response(['scorers' => [
                $this->scorerEntry(44, 'Erling Haaland', 'Norway', 27, 5, 3, 31),
                $this->scorerEntry(45, 'Mohamed Salah', 'Egypt', 22, 9, 6, 33),
                $this->scorerEntry(46, 'Cole Palmer', 'England', 18, 11, 7, 34),
            ]], 200),
        ]);

        $response = $this->getJson('/api/competitions/PL/scorers?limit=3');

        $response->assertOk();
        $response->assertJsonPath('meta.cached', false);

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertCount(3, $data);

        // Ranks are 1-based by upstream order.
        $response->assertJsonPath('data.0.rank', 1);
        $response->assertJsonPath('data.1.rank', 2);
        $response->assertJsonPath('data.2.rank', 3);

        // Top scorer counts.
        $response->assertJsonPath('data.0.goals', 27);
        $response->assertJsonPath('data.0.assists', 5);
        $response->assertJsonPath('data.0.penalties', 3);
        $response->assertJsonPath('data.0.playedMatches', 31);

        // Nested player is a normalized person object.
        $response->assertJsonPath('data.0.player.id', '44');
        $response->assertJsonPath('data.0.player.name', 'Erling Haaland');
        $response->assertJsonPath('data.0.player.nationality', 'Norway');

        // Nested team is a normalized team object.
        $response->assertJsonPath('data.0.team.id', '65');
        $response->assertJsonPath('data.0.team.name', 'Manchester City FC');
        $response->assertJsonPath('data.0.team.tla', 'MCI');

        // The limit is forwarded to the upstream call.
        Http::assertSent(function (Request $request): bool {
            $query = $request->data();

            return str_contains($request->url(), 'api.football-data.org/v4/competitions/PL/scorers')
                && (string) ($query['limit'] ?? null) === '3';
        });
    }

    // --- 3. scorers validation: limit out of range -> 422, no upstream call --

    public function test_competition_scorers_rejects_a_zero_limit_with_422_and_no_upstream_call(): void
    {
        Http::fake([
            '*/competitions/PL/scorers*' => Http::response(['scorers' => []], 200),
        ]);

        $response = $this->getJson('/api/competitions/PL/scorers?limit=0');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('limit');

        Http::assertNothingSent();
    }

    public function test_competition_scorers_rejects_an_oversized_limit_with_422_and_no_upstream_call(): void
    {
        Http::fake([
            '*/competitions/PL/scorers*' => Http::response(['scorers' => []], 200),
        ]);

        $response = $this->getJson('/api/competitions/PL/scorers?limit=500');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('limit');

        Http::assertNothingSent();
    }

    // --- 4. team detail: founded/venue/clubColors/area + squad persons -------

    public function test_team_show_returns_team_detail_with_area_and_squad(): void
    {
        Http::fake([
            '*/teams/65' => Http::response($this->teamDetail(), 200),
        ]);

        $response = $this->getJson('/api/teams/65');

        $response->assertOk();
        $response->assertJsonPath('meta.stale', false);
        $response->assertJsonPath('meta.cached', false);
        $this->assertIsString($response->json('meta.lastUpdated'));

        // data is a single normalized team-detail object (associative, not a list).
        $response->assertJsonPath('data.id', '65');
        $response->assertJsonPath('data.name', 'Manchester City FC');
        $response->assertJsonPath('data.short', 'MCI');
        $response->assertJsonPath('data.tla', 'MCI');

        // Detail-only fields.
        $response->assertJsonPath('data.founded', 1880);
        $response->assertJsonPath('data.clubColors', 'Sky Blue / White');
        $response->assertJsonPath('data.venue', 'Etihad Stadium');

        // Nested area object.
        $response->assertJsonPath('data.area.name', 'England');
        $response->assertJsonPath('data.area.flag', 'https://crests.football-data.org/770.svg');

        // squad is an array of normalized person objects.
        $squad = $response->json('data.squad');
        $this->assertIsArray($squad);
        $this->assertCount(1, $squad);
        $response->assertJsonPath('data.squad.0.id', '3754');
        $response->assertJsonPath('data.squad.0.name', 'Ederson');
        $response->assertJsonPath('data.squad.0.position', 'Goalkeeper');
        $response->assertJsonPath('data.squad.0.nationality', 'Brazil');
        $response->assertJsonPath('data.squad.0.shirtNumber', 31);
    }

    // --- 5. team matches: normalized list + uppercased status forwarded ------

    public function test_team_matches_returns_normalized_list_and_uppercases_status(): void
    {
        Http::fake([
            '*/teams/65/matches*' => Http::response($this->teamMatch(), 200),
        ]);

        $response = $this->getJson('/api/teams/65/matches?status=finished');

        $response->assertOk();
        $response->assertJsonPath('meta.cached', false);

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertCount(1, $data);

        // Same normalized match shape as the matches endpoint.
        $response->assertJsonPath('data.0.id', '538155');
        $response->assertJsonPath('data.0.status', 'FT'); // FINISHED -> FT
        $response->assertJsonPath('data.0.homeScore', 2);
        $response->assertJsonPath('data.0.awayScore', 1);
        $response->assertJsonPath('data.0.competition.code', 'PL');
        $response->assertJsonPath('data.0.home.tla', 'MCI');

        // status is uppercased before forwarding upstream.
        Http::assertSent(function (Request $request): bool {
            $query = $request->data();

            return str_contains($request->url(), 'api.football-data.org/v4/teams/65/matches')
                && ($query['status'] ?? null) === 'FINISHED';
        });
    }

    // --- 6. person: normalized person + nested currentTeam -------------------

    public function test_person_show_returns_a_normalized_person_with_team(): void
    {
        Http::fake([
            '*/persons/44' => Http::response($this->personDetail(), 200),
        ]);

        $response = $this->getJson('/api/persons/44');

        $response->assertOk();
        $response->assertJsonPath('meta.stale', false);
        $response->assertJsonPath('meta.cached', false);
        $this->assertIsString($response->json('meta.lastUpdated'));

        // data is a single normalized person object.
        $response->assertJsonPath('data.id', '44');
        $response->assertJsonPath('data.name', 'Cristiano Ronaldo');
        $response->assertJsonPath('data.firstName', 'Cristiano');
        $response->assertJsonPath('data.lastName', 'Ronaldo');
        $response->assertJsonPath('data.nationality', 'Portugal');
        $response->assertJsonPath('data.position', 'Offence');
        $response->assertJsonPath('data.shirtNumber', 7);

        // currentTeam -> nested normalized team under the `team` key.
        $response->assertJsonPath('data.team.id', '5530');
        $response->assertJsonPath('data.team.name', 'Al-Nassr FC');
        $response->assertJsonPath('data.team.tla', 'NAS');
    }

    // --- 7. cache hit (teams): second request served from cache -------------

    public function test_competition_teams_serves_second_request_from_cache_with_one_upstream_call(): void
    {
        Http::fake([
            '*/competitions/PL/teams*' => Http::response(['teams' => [
                $this->competitionTeam(65, 'Manchester City FC', 'MCI'),
            ]], 200),
        ]);

        $first = $this->getJson('/api/competitions/PL/teams');
        $second = $this->getJson('/api/competitions/PL/teams');

        // Only the first request reaches upstream; the second is served from cache.
        Http::assertSentCount(1);

        $first->assertOk()->assertJsonPath('meta.cached', false);
        $second->assertOk()->assertJsonPath('meta.cached', true);
        $second->assertJsonPath('meta.stale', false);

        $this->assertSame($first->json('data'), $second->json('data'));
    }

    // --- 8. hard miss (person): 429 x3 with empty cache -> 503, null, stale --

    public function test_person_show_returns_503_on_hard_miss_with_empty_cache(): void
    {
        // Default retries=2 => 3 upstream attempts (retries + 1) before the retry
        // helper throws RequestException, which the service catches and returns null.
        // With no last-known-good cached, cached() yields data=null => envelope 503.
        Http::fake([
            '*/persons/44' => Http::sequence()
                ->push(['error' => 'rate limited'], 429)
                ->push(['error' => 'rate limited'], 429)
                ->push(['error' => 'rate limited'], 429)
                ->whenEmpty(Http::response(['unexpected' => true], 500)),
        ]);

        $response = $this->getJson('/api/persons/44');

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
