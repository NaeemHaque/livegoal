<?php

namespace Tests\Feature;

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
}
