<?php

namespace Tests\Feature\Seo;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Covers the server-side SEO shell: every SPA route is served by
 * SeoShellController, which renders the app blade with a per-URL SeoMeta
 * (title, description, canonical, robots, Open Graph, JSON-LD) resolved from
 * the existing cache. Crawlers get real, route-specific metadata; the SPA mount
 * point (`id="app"`) is always present so the Vue app still boots.
 *
 * Reads are cache-only (FootballData::peek) — a crawler flood must never reach
 * the upstream API, so preventStrayRequests guards that.
 */
class SeoShellTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Http::preventStrayRequests();
    }

    /**
     * Seed the cache the way FootballData::cached() does: the raw upstream
     * payload wrapped as ['data' => ..., 'at' => ...] under the fresh key.
     *
     * @param  array<string, mixed>  $payload
     */
    private function cacheUpstream(string $key, array $payload): void
    {
        Cache::put("fd:{$key}", ['data' => $payload, 'at' => '2026-06-10T12:00:00+00:00'], 600);
    }

    /** @return array<string, mixed> */
    private function upstreamMatch(): array
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

    // --- Home: site-wide schema + crawlable nav -----------------------------

    public function test_home_has_title_canonical_open_graph_and_website_schema(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('id="app"', false)
            ->assertSee('<link rel="canonical"', false)
            ->assertSee('property="og:title"', false)
            ->assertSee('name="twitter:card"', false)
            ->assertSee('"@type":"WebSite"', false)
            ->assertSee('"@type":"Organization"', false);
    }

    public function test_home_includes_crawlable_noscript_navigation(): void
    {
        $this->get('/')
            ->assertSee('<noscript>', false)
            ->assertSee('href="'.url('/competitions').'"', false)
            ->assertSee('href="'.url('/competition/PL').'"', false);
    }

    // --- Match page: rich, entity-specific meta -----------------------------

    public function test_cached_match_has_event_title_and_sportsevent_schema(): void
    {
        $this->cacheUpstream('match:1', $this->upstreamMatch());

        $response = $this->get('/match/1');

        $response->assertOk()
            ->assertSee('<title>Arsenal FC vs Chelsea FC', false)
            // Canonical is the keyword-rich slug URL, not the bare id.
            ->assertSee('<link rel="canonical" href="'.url('/match/1-arsenal-fc-vs-chelsea-fc').'">', false)
            ->assertSee('"@type":"SportsEvent"', false)
            ->assertSee('"@type":"BreadcrumbList"', false)
            ->assertSee('property="og:title"', false);

        // A cached, real entity is indexable.
        $response->assertDontSee('name="robots" content="noindex', false);
    }

    public function test_match_canonical_is_slug_url_ignoring_query_string(): void
    {
        $this->cacheUpstream('match:1', $this->upstreamMatch());

        $this->get('/match/1?utm_source=newsletter&fbclid=abc')
            ->assertSee('<link rel="canonical" href="'.url('/match/1-arsenal-fc-vs-chelsea-fc').'">', false);
    }

    public function test_slugged_match_url_resolves_the_same_entity(): void
    {
        $this->cacheUpstream('match:1', $this->upstreamMatch());

        // A keyword URL (even with a stale/wrong slug) resolves by its numeric id.
        $this->get('/match/1-arsenal-fc-vs-chelsea-fc')
            ->assertOk()
            ->assertSee('<title>Arsenal FC vs Chelsea FC', false)
            ->assertSee('<link rel="canonical" href="'.url('/match/1-arsenal-fc-vs-chelsea-fc').'">', false);
    }

    public function test_uncached_match_is_noindex_but_still_serves_the_shell(): void
    {
        $this->get('/match/999999')
            ->assertOk()
            ->assertSee('id="app"', false)
            ->assertSee('name="robots" content="noindex', false);
    }

    // --- Competition page: detail cache OR competitions-list fallback --------

    public function test_cached_competition_detail_has_name_in_title(): void
    {
        $this->cacheUpstream('competition:PL', [
            'id' => 2021, 'name' => 'Premier League', 'code' => 'PL', 'type' => 'LEAGUE',
        ]);

        $this->get('/competition/PL')
            ->assertOk()
            ->assertSee('<title>Premier League', false)
            ->assertSee('"@type":"SportsOrganization"', false)
            ->assertDontSee('name="robots" content="noindex', false);
    }

    public function test_competition_resolves_name_from_competitions_list_when_detail_cold(): void
    {
        // Only the warmed competitions LIST is cached, not the per-competition detail.
        $this->cacheUpstream('competitions', [
            'competitions' => [
                ['id' => 2021, 'area' => ['name' => 'England'], 'name' => 'Premier League', 'code' => 'PL', 'type' => 'LEAGUE'],
            ],
        ]);

        $this->get('/competition/PL')
            ->assertOk()
            ->assertSee('<title>Premier League', false)
            ->assertDontSee('name="robots" content="noindex', false);
    }

    // --- Team & player pages ------------------------------------------------

    public function test_cached_team_has_name_in_title_and_sportsteam_schema(): void
    {
        $this->cacheUpstream('team:57', [
            'id' => 57, 'name' => 'Arsenal FC', 'tla' => 'ARS',
            'area' => ['name' => 'England'], 'squad' => [],
        ]);

        $this->get('/team/57')
            ->assertOk()
            ->assertSee('<title>Arsenal FC', false)
            ->assertSee('"@type":"SportsTeam"', false);
    }

    public function test_cached_player_has_name_in_title_and_person_schema(): void
    {
        $this->cacheUpstream('person:44', [
            'id' => 44, 'name' => 'Cristiano Ronaldo', 'position' => 'Centre-Forward',
            'nationality' => 'Portugal',
        ]);

        $this->get('/player/44')
            ->assertOk()
            ->assertSee('<title>Cristiano Ronaldo', false)
            ->assertSee('"@type":"Person"', false);
    }

    // --- Hub pages are indexable with distinct titles -----------------------

    public function test_scorers_hub_has_distinct_indexable_title(): void
    {
        $this->get('/scorers')
            ->assertOk()
            ->assertSee('<title>Top Scorers', false)
            ->assertDontSee('name="robots" content="noindex', false);
    }

    // --- Utility pages are noindex ------------------------------------------

    public function test_settings_is_noindex(): void
    {
        $this->get('/settings')
            ->assertOk()
            ->assertSee('name="robots" content="noindex', false);
    }

    public function test_favorites_is_noindex(): void
    {
        $this->get('/favorites')
            ->assertOk()
            ->assertSee('name="robots" content="noindex', false);
    }

    // --- Crawlable date pages -----------------------------------------------

    public function test_date_page_has_dated_title_and_canonical(): void
    {
        $this->cacheUpstream('competition:WC:matches', [
            'matches' => [[
                'id' => 20,
                'competition' => ['id' => 2000, 'name' => 'FIFA World Cup', 'code' => 'WC', 'type' => 'CUP'],
                'homeTeam' => ['id' => 1, 'name' => 'Mexico', 'tla' => 'MEX'],
                'awayTeam' => ['id' => 2, 'name' => 'Canada', 'tla' => 'CAN'],
                'status' => 'TIMED', 'utcDate' => '2026-06-12T18:00:00Z',
                'score' => ['fullTime' => ['home' => null, 'away' => null], 'winner' => null],
            ]],
        ]);

        $this->get('/matches/2026-06-12')
            ->assertOk()
            ->assertSee('<title>Football Matches on 12 June 2026', false)
            ->assertSee('<link rel="canonical" href="'.url('/matches/2026-06-12').'">', false)
            ->assertDontSee('name="robots" content="noindex', false);
    }

    public function test_date_page_without_fixtures_is_noindex(): void
    {
        // No featured feeds cached → no matches that day → thin page → noindex.
        $this->get('/matches/2030-01-01')
            ->assertOk()
            ->assertSee('name="robots" content="noindex', false);
    }

    public function test_invalid_date_path_returns_404(): void
    {
        $this->get('/matches/not-a-date')->assertNotFound();
    }
}
