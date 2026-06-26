<?php

namespace Tests\Feature\Seo;

use App\Services\Seo\IndexNow;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * IndexNow instant submission (Bing / Yandex). Disabled until INDEXNOW_KEY is
 * set: with no key, nothing is ever submitted and the key file 404s; with a key,
 * changed match URLs are POSTed to the endpoint and the key file verifies.
 */
class IndexNowTest extends TestCase
{
    /** @return list<array<string, mixed>> */
    private function changedMatches(): array
    {
        return [[
            'id' => '20',
            'home' => ['id' => '1', 'name' => 'Mexico'],
            'away' => ['id' => '2', 'name' => 'Canada'],
            'status' => 'LIVE',
        ]];
    }

    public function test_submit_posts_changed_urls_when_key_is_configured(): void
    {
        config(['services.indexnow.key' => 'abcdef0123456789abcdef0123456789']);
        Http::fake();

        app(IndexNow::class)->submitMatches($this->changedMatches());

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return str_contains((string) $request->url(), 'indexnow')
                && $data['key'] === 'abcdef0123456789abcdef0123456789'
                && in_array(url('/match/20-mexico-vs-canada'), $data['urlList'], true)
                && in_array(url('/'), $data['urlList'], true);
        });
    }

    public function test_submit_dedupes_repeated_urls_within_the_window(): void
    {
        config(['services.indexnow.key' => 'abcdef0123456789abcdef0123456789']);
        Http::fake();

        $indexNow = app(IndexNow::class);
        $indexNow->submitMatches($this->changedMatches());
        // Same change again on the next poll — nothing new, so no second ping.
        $indexNow->submitMatches($this->changedMatches());

        Http::assertSentCount(1);
    }

    public function test_submit_is_a_noop_without_a_key(): void
    {
        config(['services.indexnow.key' => null]);
        Http::fake();

        app(IndexNow::class)->submitMatches($this->changedMatches());

        Http::assertNothingSent();
    }

    public function test_key_file_serves_the_configured_key(): void
    {
        config(['services.indexnow.key' => 'abcdef0123456789abcdef0123456789']);

        $this->get('/abcdef0123456789abcdef0123456789.txt')
            ->assertOk()
            ->assertSee('abcdef0123456789abcdef0123456789', false);
    }

    public function test_key_file_404s_for_a_wrong_key(): void
    {
        config(['services.indexnow.key' => 'abcdef0123456789abcdef0123456789']);

        $this->get('/0000000000000000deadbeefdeadbeef.txt')->assertNotFound();
    }

    public function test_key_file_404s_when_indexnow_is_disabled(): void
    {
        config(['services.indexnow.key' => null]);

        $this->get('/abcdef0123456789abcdef0123456789.txt')->assertNotFound();
    }
}
