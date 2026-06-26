<?php

namespace App\Services\Seo;

use App\Seo\Slug;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Submits changed URLs to IndexNow (Bing / Yandex / Seznam) so result pages are
 * re-crawled within minutes of a score change. A no-op until INDEXNOW_KEY is set,
 * so dev and tests stay silent and prod opts in by configuring the key.
 */
class IndexNow
{
    /** Don't resubmit the same URL within this window (seconds) — caps pings on busy days. */
    private const DEDUP_TTL = 600;

    public function enabled(): bool
    {
        return $this->key() !== '';
    }

    public function key(): string
    {
        $key = Config::get('services.indexnow.key');

        return is_string($key) ? $key : '';
    }

    /**
     * Submit the pages affected by a set of changed matches: each match page,
     * plus the home and matches hubs that list them.
     *
     * @param  list<array<array-key, mixed>>  $matches  Normalized matches whose score changed.
     */
    public function submitMatches(array $matches): void
    {
        if ($matches === [] || ! $this->enabled()) {
            return;
        }

        $urls = [url('/'), url('/matches')];

        foreach ($matches as $match) {
            $id = $this->str($match['id'] ?? null);
            $home = $this->str(data_get($match, 'home.name'));
            $away = $this->str(data_get($match, 'away.name'));

            if ($id !== '' && $home !== '' && $away !== '') {
                $urls[] = Slug::url('match', $id, "{$home} vs {$away}");
            }
        }

        $this->submit($urls);
    }

    /**
     * Submit a list of absolute URLs. Failures are logged, never thrown — a
     * search-engine ping must never break the polling flow.
     *
     * @param  list<string>  $urls
     */
    public function submit(array $urls): void
    {
        $key = $this->key();

        if ($key === '') {
            return;
        }

        // Drop URLs already submitted within the dedup window — Cache::add is
        // only true the first time a key is set, so /, /matches and repeated
        // match URLs aren't re-pinged on every 30s poll.
        $fresh = [];

        foreach (array_unique(array_filter($urls, fn (string $url): bool => $url !== '')) as $url) {
            if (Cache::add('indexnow:sent:'.md5($url), true, self::DEDUP_TTL)) {
                $fresh[] = $url;
            }
        }

        if ($fresh === []) {
            return;
        }

        $host = (string) parse_url(url('/'), PHP_URL_HOST);

        try {
            Http::asJson()->connectTimeout(3)->timeout(5)->post(Config::string('services.indexnow.endpoint'), [
                'host' => $host,
                'key' => $key,
                'keyLocation' => url("/{$key}.txt"),
                'urlList' => array_slice($fresh, 0, 100),
            ]);
        } catch (\Throwable $e) {
            Log::warning('IndexNow submit failed', ['message' => $e->getMessage()]);
        }
    }

    private function str(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
