<?php

namespace Tests\Feature\Seo;

use Tests\TestCase;

/**
 * Covers the SEO discovery surface: a dynamic robots.txt that points crawlers at
 * the sitemap and disallows utility/API paths, and an XML sitemap listing the
 * indexable hub and competition URLs. Both are served by SitemapController and
 * never touch the upstream API.
 */
class SitemapTest extends TestCase
{
    public function test_sitemap_is_xml_and_lists_hub_and_competition_urls(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $this->assertStringContainsString('application/xml', (string) $response->headers->get('Content-Type'));

        $body = $response->getContent();
        $this->assertIsString($body);
        $this->assertStringContainsString('<loc>'.url('/').'</loc>', $body);
        $this->assertStringContainsString('<loc>'.url('/competitions').'</loc>', $body);
        $this->assertStringContainsString('<loc>'.url('/scorers').'</loc>', $body);
        // Every free-tier competition code resolves to a crawlable detail URL.
        $this->assertStringContainsString('<loc>'.url('/competition/PL').'</loc>', $body);
        $this->assertStringContainsString('<loc>'.url('/competition/WC').'</loc>', $body);
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
