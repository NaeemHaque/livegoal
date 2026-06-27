<?php

namespace Tests\Feature\Seo;

use App\Seo\OgImage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Dynamic per-match Open Graph images. Built cache-only from the match feed, so
 * a social scraper hitting the image never reaches the upstream API; a missing
 * match falls back to the static og-image.png.
 */
class OgImageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function cacheUpstream(string $key, array $payload): void
    {
        Cache::put("fd:{$key}", ['data' => $payload, 'at' => '2026-06-26T12:00:00+00:00'], 600);
    }

    public function test_match_og_image_is_a_1200x630_png(): void
    {
        $this->cacheUpstream('match:2', [
            'id' => 2,
            'competition' => ['id' => 2000, 'name' => 'FIFA World Cup', 'code' => 'WC', 'type' => 'CUP'],
            'homeTeam' => ['id' => 1, 'name' => 'Mexico', 'tla' => 'MEX'],
            'awayTeam' => ['id' => 2, 'name' => 'Canada', 'tla' => 'CAN'],
            'status' => 'FINISHED',
            'utcDate' => '2026-06-26T15:00:00Z',
            'score' => ['fullTime' => ['home' => 2, 'away' => 1], 'winner' => 'HOME_TEAM'],
        ]);

        $response = $this->get('/og/match/2');

        $response->assertOk();
        $this->assertSame('image/png', $response->headers->get('Content-Type'));

        $body = (string) $response->getContent();
        $size = getimagesizefromstring($body);
        $this->assertIsArray($size);
        $this->assertSame(1200, $size[0]);
        $this->assertSame(630, $size[1]);
    }

    public function test_match_og_image_renders_from_feed_without_single_cache(): void
    {
        // Only the competition feed is warm — NOT the per-match cache. The OG
        // card must still render (mirrors the SEO page's feed fallback), so a
        // shared link shows the teams before anyone has opened the match.
        $this->cacheUpstream('competition:WC:matches', [
            'matches' => [[
                'id' => 88,
                'competition' => ['id' => 2000, 'name' => 'FIFA World Cup', 'code' => 'WC', 'type' => 'CUP'],
                'homeTeam' => ['id' => 1, 'name' => 'Mexico', 'tla' => 'MEX'],
                'awayTeam' => ['id' => 2, 'name' => 'Canada', 'tla' => 'CAN'],
                'status' => 'TIMED', 'utcDate' => '2026-06-28T18:00:00Z',
                'score' => ['fullTime' => ['home' => null, 'away' => null], 'winner' => null],
            ]],
        ]);

        $response = $this->get('/og/match/88');

        $response->assertOk();
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
        $size = getimagesizefromstring((string) $response->getContent());
        $this->assertIsArray($size);
        $this->assertSame(1200, $size[0]);
        $this->assertSame(630, $size[1]);
    }

    public function test_uncached_match_og_image_falls_back_to_static(): void
    {
        $this->get('/og/match/999999')
            ->assertRedirect(url(config('seo.og_image')));
    }

    public function test_render_failure_falls_back_to_static(): void
    {
        // Simulate a host without GD / a TTF font: the renderer returns null.
        $this->app->instance(OgImage::class, new class extends OgImage
        {
            public function card(string $eyebrow, string $title, string $subtitle): ?string
            {
                return null;
            }
        });

        $this->cacheUpstream('match:5', [
            'id' => 5,
            'competition' => ['id' => 2000, 'name' => 'FIFA World Cup', 'code' => 'WC', 'type' => 'CUP'],
            'homeTeam' => ['id' => 1, 'name' => 'Mexico', 'tla' => 'MEX'],
            'awayTeam' => ['id' => 2, 'name' => 'Canada', 'tla' => 'CAN'],
            'status' => 'TIMED', 'utcDate' => '2026-06-28T18:00:00Z',
            'score' => ['fullTime' => ['home' => null, 'away' => null], 'winner' => null],
        ]);

        $this->get('/og/match/5')
            ->assertRedirect(url(config('seo.og_image')));
    }

    public function test_match_page_references_its_dynamic_og_image(): void
    {
        $this->withoutVite();

        $this->cacheUpstream('match:1', [
            'id' => 1,
            'competition' => ['id' => 2021, 'name' => 'Premier League', 'code' => 'PL', 'type' => 'LEAGUE'],
            'homeTeam' => ['id' => 57, 'name' => 'Arsenal FC', 'tla' => 'ARS'],
            'awayTeam' => ['id' => 61, 'name' => 'Chelsea FC', 'tla' => 'CHE'],
            'status' => 'TIMED',
            'utcDate' => '2026-06-26T19:00:00Z',
            'score' => ['fullTime' => ['home' => null, 'away' => null], 'winner' => null],
        ]);

        $this->get('/match/1')
            ->assertOk()
            ->assertSee('property="og:image" content="'.url('/og/match/1').'"', false)
            ->assertSee('name="twitter:image" content="'.url('/og/match/1').'"', false);
    }
}
