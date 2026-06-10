<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Covers the server-rendered editorial content pages (guides, World Cup
 * explainers, trust) — the evergreen AEO/GEO layer. Each is crawlable HTML with
 * per-page SEO metadata and a breadcrumb, and they appear in the sitemap.
 */
class ContentPagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guides_index_lists_guides(): void
    {
        $this->get('/guides')
            ->assertOk()
            ->assertSee('Football guides', false)
            ->assertSee('href="'.url('/guides/world-cup-2026-format-explained').'"', false);
    }

    public function test_guide_page_renders_with_meta_and_breadcrumb(): void
    {
        $this->get('/guides/world-cup-2026-format-explained')
            ->assertOk()
            ->assertSee('<h1>World Cup 2026 format explained</h1>', false)
            ->assertSee('<link rel="canonical" href="'.url('/guides/world-cup-2026-format-explained').'">', false)
            ->assertSee('"@type":"BreadcrumbList"', false)
            ->assertDontSee('name="robots" content="noindex', false);
    }

    public function test_how_to_watch_page_shows_verified_broadcasters(): void
    {
        $this->get('/guides/how-to-watch-world-cup-2026-free')
            ->assertOk()
            ->assertSee('BBC', false)
            ->assertSee('SBS', false);
    }

    public function test_glossary_page_renders(): void
    {
        $this->get('/guides/football-glossary')
            ->assertOk()
            ->assertSee('Goal difference', false);
    }

    public function test_trust_pages_render(): void
    {
        $this->get('/about')->assertOk()->assertSee('About LiveGoal', false);
        $this->get('/how-our-data-works')->assertOk()->assertSee('football-data.org', false);
        $this->get('/contact')->assertOk()->assertSee('Contact LiveGoal', false);
    }

    public function test_unknown_guide_returns_404(): void
    {
        $this->get('/guides/does-not-exist')->assertNotFound();
    }

    public function test_sitemap_includes_content_pages(): void
    {
        $body = (string) $this->get('/sitemap.xml')->getContent();

        $this->assertStringContainsString('<loc>'.url('/guides').'</loc>', $body);
        $this->assertStringContainsString('<loc>'.url('/guides/world-cup-2026-format-explained').'</loc>', $body);
        $this->assertStringContainsString('<loc>'.url('/about').'</loc>', $body);
    }

    // --- Per-term glossary pages --------------------------------------------

    public function test_glossary_term_page_renders_with_related_links(): void
    {
        $this->get('/guides/what-is-offside')
            ->assertOk()
            ->assertSee('<h1>What is offside in football?</h1>', false)
            ->assertSee('More football terms', false)
            ->assertSee('href="'.url('/guides/what-is-goal-difference').'"', false)
            ->assertSee('<link rel="canonical" href="'.url('/guides/what-is-offside').'">', false);
    }

    public function test_glossary_index_links_to_term_pages(): void
    {
        $this->get('/guides/football-glossary')
            ->assertOk()
            ->assertSee('href="'.url('/guides/what-is-offside').'"', false);
    }

    // --- Per-country "how to watch free" pages ------------------------------

    public function test_watch_country_page_renders(): void
    {
        $this->get('/guides/how-to-watch-world-cup-2026-free/uk')
            ->assertOk()
            ->assertSee('How to watch the World Cup 2026 free in the UK', false)
            ->assertSee('BBC and ITV', false)
            ->assertSee('<link rel="canonical" href="'.url('/guides/how-to-watch-world-cup-2026-free/uk').'">', false);
    }

    public function test_watch_hub_links_to_country_pages(): void
    {
        $this->get('/guides/how-to-watch-world-cup-2026-free')
            ->assertOk()
            ->assertSee('href="'.url('/guides/how-to-watch-world-cup-2026-free/uk').'"', false);
    }

    public function test_unknown_watch_country_returns_404(): void
    {
        $this->get('/guides/how-to-watch-world-cup-2026-free/atlantis')->assertNotFound();
    }

    // --- Live data embedded in the World Cup explainers ---------------------

    public function test_wc_explainer_embeds_live_fixtures(): void
    {
        Cache::put('fd:competition:WC:matches', [
            'data' => ['matches' => [[
                'id' => 30,
                'competition' => ['id' => 2000, 'name' => 'FIFA World Cup', 'code' => 'WC', 'type' => 'CUP'],
                'homeTeam' => ['id' => 1, 'name' => 'Mexico', 'tla' => 'MEX'],
                'awayTeam' => ['id' => 2, 'name' => 'Canada', 'tla' => 'CAN'],
                'status' => 'TIMED', 'utcDate' => '2026-06-30T18:00:00Z',
                'score' => ['fullTime' => ['home' => null, 'away' => null], 'winner' => null],
            ]]],
            'at' => '2026-06-11T12:00:00+00:00',
        ], 600);

        $this->get('/guides/world-cup-2026-format-explained')
            ->assertOk()
            ->assertSee('live right now', false)
            ->assertSee('Mexico', false);
    }

    public function test_sitemap_includes_glossary_and_watch_pages(): void
    {
        $body = (string) $this->get('/sitemap.xml')->getContent();

        $this->assertStringContainsString('<loc>'.url('/guides/what-is-offside').'</loc>', $body);
        $this->assertStringContainsString('<loc>'.url('/guides/how-to-watch-world-cup-2026-free/uk').'</loc>', $body);
    }
}
