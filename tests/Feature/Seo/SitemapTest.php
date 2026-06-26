<?php

namespace Tests\Feature\Seo;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Covers the SEO discovery surface: a dynamic robots.txt that points crawlers at
 * the sitemap, and a sitemap index that fans out to child sitemaps (core hub /
 * competition / content pages, plus the match and team entity pages). Every URL
 * carries a <lastmod> freshness stamp. All of it is built cache-only, so a
 * crawler fetching the sitemap never reaches the upstream API.
 */
class SitemapTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    /**
     * Seed the cache the way FootballData::cached() does.
     *
     * @param  array<string, mixed>  $payload
     */
    private function cacheUpstream(string $key, array $payload): void
    {
        Cache::put("fd:{$key}", ['data' => $payload, 'at' => '2026-06-26T12:00:00+00:00'], 600);
    }

    public function test_sitemap_index_fans_out_to_child_sitemaps(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $this->assertStringContainsString('application/xml', (string) $response->headers->get('Content-Type'));

        $body = $response->getContent();
        $this->assertIsString($body);
        $this->assertStringContainsString('<sitemapindex', $body);
        $this->assertStringContainsString('<loc>'.url('/sitemap-core.xml').'</loc>', $body);
        $this->assertStringContainsString('<loc>'.url('/sitemap-matches.xml').'</loc>', $body);
        $this->assertStringContainsString('<loc>'.url('/sitemap-teams.xml').'</loc>', $body);
        $this->assertStringContainsString('<lastmod>', $body);
    }

    public function test_core_sitemap_lists_hub_and_competition_urls_with_lastmod(): void
    {
        $response = $this->get('/sitemap-core.xml');

        $response->assertOk();
        $body = $response->getContent();
        $this->assertIsString($body);

        $this->assertStringContainsString('<loc>'.url('/').'</loc>', $body);
        $this->assertStringContainsString('<loc>'.url('/competitions').'</loc>', $body);
        $this->assertStringContainsString('<loc>'.url('/scorers').'</loc>', $body);
        // Every free-tier competition code resolves to a crawlable detail URL.
        $this->assertStringContainsString('<loc>'.url('/competition/PL').'</loc>', $body);
        $this->assertStringContainsString('<loc>'.url('/competition/WC').'</loc>', $body);
        // Editorial content is discoverable too.
        $this->assertStringContainsString('<loc>'.url('/guides').'</loc>', $body);
        // Freshness signal present on every entry.
        $this->assertStringContainsString('<lastmod>', $body);
    }

    public function test_matches_sitemap_lists_cached_match_pages(): void
    {
        $this->cacheUpstream('competition:WC:matches', [
            'matches' => [[
                'id' => 20,
                'competition' => ['id' => 2000, 'name' => 'FIFA World Cup', 'code' => 'WC', 'type' => 'CUP'],
                'homeTeam' => ['id' => 1, 'name' => 'Mexico', 'tla' => 'MEX'],
                'awayTeam' => ['id' => 2, 'name' => 'Canada', 'tla' => 'CAN'],
                'status' => 'TIMED', 'utcDate' => '2026-06-26T18:00:00Z',
                'score' => ['fullTime' => ['home' => null, 'away' => null], 'winner' => null],
            ]],
        ]);

        $response = $this->get('/sitemap-matches.xml');

        $response->assertOk();
        $body = $response->getContent();
        $this->assertIsString($body);
        $this->assertStringContainsString('<loc>'.url('/match/20-mexico-vs-canada').'</loc>', $body);
        $this->assertStringContainsString('<lastmod>', $body);
    }

    public function test_teams_sitemap_lists_cached_team_pages(): void
    {
        $this->cacheUpstream('competition:WC:matches', [
            'matches' => [[
                'id' => 20,
                'competition' => ['id' => 2000, 'name' => 'FIFA World Cup', 'code' => 'WC', 'type' => 'CUP'],
                'homeTeam' => ['id' => 1, 'name' => 'Mexico', 'tla' => 'MEX'],
                'awayTeam' => ['id' => 2, 'name' => 'Canada', 'tla' => 'CAN'],
                'status' => 'TIMED', 'utcDate' => '2026-06-26T18:00:00Z',
                'score' => ['fullTime' => ['home' => null, 'away' => null], 'winner' => null],
            ]],
        ]);

        $response = $this->get('/sitemap-teams.xml');

        $response->assertOk();
        $body = $response->getContent();
        $this->assertIsString($body);
        $this->assertStringContainsString('<loc>'.url('/team/1-mexico').'</loc>', $body);
        $this->assertStringContainsString('<loc>'.url('/team/2-canada').'</loc>', $body);
    }

    public function test_news_sitemap_lists_recent_results(): void
    {
        $this->travelTo(Carbon::parse('2026-06-26 18:00:00'));

        $this->cacheUpstream('competition:WC:matches', [
            'matches' => [[
                'id' => 20,
                'competition' => ['id' => 2000, 'name' => 'FIFA World Cup', 'code' => 'WC', 'type' => 'CUP'],
                'homeTeam' => ['id' => 1, 'name' => 'Mexico', 'tla' => 'MEX'],
                'awayTeam' => ['id' => 2, 'name' => 'Canada', 'tla' => 'CAN'],
                'status' => 'FINISHED', 'utcDate' => '2026-06-26T15:00:00Z',
                'score' => ['fullTime' => ['home' => 2, 'away' => 1], 'winner' => 'HOME_TEAM'],
            ]],
        ]);

        $response = $this->get('/sitemap-news.xml');

        $response->assertOk();
        $body = $response->getContent();
        $this->assertIsString($body);
        $this->assertStringContainsString('xmlns:news=', $body);
        $this->assertStringContainsString('<news:name>LiveGoal</news:name>', $body);
        $this->assertStringContainsString('<loc>'.url('/match/20-mexico-vs-canada').'</loc>', $body);
        $this->assertStringContainsString('Full time: Mexico 2', $body);

        $this->travelBack();
    }

    public function test_news_sitemap_excludes_old_results(): void
    {
        $this->travelTo(Carbon::parse('2026-06-26 18:00:00'));

        // Finished a week ago — outside the 48h Google News window.
        $this->cacheUpstream('competition:WC:matches', [
            'matches' => [[
                'id' => 21,
                'competition' => ['id' => 2000, 'name' => 'FIFA World Cup', 'code' => 'WC', 'type' => 'CUP'],
                'homeTeam' => ['id' => 1, 'name' => 'Mexico', 'tla' => 'MEX'],
                'awayTeam' => ['id' => 2, 'name' => 'Canada', 'tla' => 'CAN'],
                'status' => 'FINISHED', 'utcDate' => '2026-06-19T15:00:00Z',
                'score' => ['fullTime' => ['home' => 2, 'away' => 1], 'winner' => 'HOME_TEAM'],
            ]],
        ]);

        $this->get('/sitemap-news.xml')
            ->assertOk()
            ->assertDontSee('/match/21-mexico-vs-canada', false);

        $this->travelBack();
    }

    public function test_robots_points_at_sitemap_and_disallows_utility_paths(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $body = $response->getContent();
        $this->assertIsString($body);

        $this->assertStringContainsString('Sitemap: '.url('/sitemap.xml'), $body);
        $this->assertStringContainsString('Disallow: /api/', $body);
        $this->assertStringContainsString('Disallow: /scheduler/', $body);
        $this->assertStringContainsString('Disallow: /settings', $body);
    }
}
