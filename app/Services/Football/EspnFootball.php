<?php

namespace App\Services\Football;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client for ESPN's free, keyless soccer endpoints (site.api.espn.com).
 *
 * Unlike football-data.org's free tier, ESPN exposes a real match clock and
 * official event minutes (goals/cards stamped with the minute they occurred).
 * This is a proof-of-concept live source — football-data.org stays the source
 * of record and the fallback. The API is unofficial (no key, no SLA): every
 * failure is treated as "no data" so the caller can fall back gracefully.
 */
class EspnFootball
{
    /**
     * Fetch a resource from ESPN. Returns decoded JSON, or null on any failure.
     *
     * @param  array<string, mixed>  $query
     * @return array<array-key, mixed>|null
     */
    public function get(string $path, array $query = []): ?array
    {
        try {
            $response = Http::baseUrl(Config::string('football.espn.base_url'))
                ->connectTimeout(Config::integer('football.espn.connect_timeout'))
                ->timeout(Config::integer('football.espn.timeout'))
                ->acceptJson()
                ->get(ltrim($path, '/'), $query);
        } catch (RequestException|ConnectionException $e) {
            Log::warning('espn request failed', ['path' => $path, 'message' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        /** @var array<array-key, mixed> */
        return $response->json();
    }

    /**
     * The ESPN league slug for a football-data competition code, or null when
     * the competition isn't mapped.
     */
    public function slugFor(string $competitionCode): ?string
    {
        /** @var array<string, string> $slugs */
        $slugs = Config::array('football.espn.slugs');

        return $slugs[strtoupper($competitionCode)] ?? null;
    }

    /**
     * A league's scoreboard, optionally scoped to a Y-m-d date. Cached briefly
     * so a burst of match-detail views costs one upstream call.
     *
     * @return array<array-key, mixed>|null
     */
    public function scoreboard(string $slug, ?string $date = null): ?array
    {
        $query = $date !== null ? ['dates' => str_replace('-', '', $date)] : [];
        $key = 'espn:scoreboard:'.$slug.':'.($date ?? 'today');

        $hit = Cache::get($key);

        if (is_array($hit)) {
            return $hit;
        }

        $data = $this->get("/{$slug}/scoreboard", $query);

        if ($data !== null) {
            Cache::put($key, $data, Config::integer('football.espn.ttl.scoreboard'));
        }

        return $data;
    }

    /**
     * The full event summary (keyEvents timeline + rosters) for one ESPN event.
     *
     * @return array<array-key, mixed>|null
     */
    public function summary(string $slug, string $eventId): ?array
    {
        $key = 'espn:summary:'.$slug.':'.$eventId;

        $hit = Cache::get($key);

        if (is_array($hit)) {
            return $hit;
        }

        $data = $this->get("/{$slug}/summary", ['event' => $eventId]);

        if ($data !== null) {
            Cache::put($key, $data, Config::integer('football.espn.ttl.summary'));
        }

        return $data;
    }
}
