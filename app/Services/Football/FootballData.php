<?php

namespace App\Services\Football;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin client for football-data.org v4.
 *
 * The only thing in the app that talks to the upstream API: authenticates with
 * the server-side token, retries 429/connection failures with backoff, logs and
 * swallows errors (never throws to the caller), and can serve the last-known-good
 * payload from cache when upstream is unavailable.
 */
class FootballData
{
    /**
     * Fetch a resource from the upstream API.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>|null Decoded JSON, or null on failure.
     */
    public function get(string $path, array $query = []): ?array
    {
        try {
            $response = Http::baseUrl(Config::string('football.base_url'))
                ->withHeaders(['X-Auth-Token' => Config::string('football.token')])
                ->connectTimeout(Config::integer('football.connect_timeout'))
                ->timeout(Config::integer('football.timeout'))
                ->acceptJson()
                ->retry(
                    Config::integer('football.retries') + 1,
                    Config::integer('football.retry_delay_ms'),
                    fn (\Throwable $e): bool => $e instanceof ConnectionException
                        || ($e instanceof RequestException && $e->response->status() === 429),
                )
                ->get(ltrim($path, '/'), $query);
        } catch (RequestException $e) {
            Log::warning('football-data request failed', [
                'path' => $path,
                'status' => $e->response->status(),
            ]);

            return null;
        } catch (ConnectionException $e) {
            Log::warning('football-data connection failed', [
                'path' => $path,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        /** @var array<string, mixed> */
        return $response->json();
    }

    /**
     * Cache-served GET. Returns a fresh value (from cache within its TTL, or a
     * fresh upstream fill); on upstream failure falls back to the last-known-good
     * payload flagged as stale. A failure is never written under the fresh key, so
     * the next request retries upstream instead of pinning stale data for the TTL.
     *
     * @param  array<string, mixed>  $query
     */
    public function cached(string $key, int $ttl, string $path, array $query = [], bool $refresh = true): Result
    {
        $base = $this->cacheKey($key, $query);
        $freshKey = "fd:{$base}";
        $lastKey = "fd:last:{$base}";

        $hit = Cache::get($freshKey);

        if (is_array($hit) && is_array($hit['data'] ?? null) && is_string($hit['at'] ?? null)) {
            return new Result($hit['data'], stale: false, cached: true, lastUpdated: $hit['at']);
        }

        // Read-only callers (the homepage aggregation) must not block a browser
        // request on the rate-limited upstream once there is something to show:
        // serve the last-known-good immediately and let the scheduled warmer
        // refresh the fresh copy. Only a truly cold feed (no last-good) falls
        // through to a one-off fetch, so first-ever loads still populate.
        if (! $refresh) {
            $served = $this->lastGood($lastKey);

            if ($served->data !== null) {
                return $served;
            }
        }

        $data = $this->get($path, $query);

        if ($data !== null) {
            $entry = ['data' => $data, 'at' => Date::now()->toIso8601String()];
            Cache::put($freshKey, $entry, $ttl);
            Cache::put($lastKey, $entry, Config::integer('football.last_good_ttl'));

            return new Result($data, stale: false, cached: false, lastUpdated: $entry['at']);
        }

        return $this->lastGood($lastKey);
    }

    /**
     * The last-known-good payload (flagged stale), or an empty result when none
     * has ever been stored.
     */
    private function lastGood(string $lastKey): Result
    {
        $last = Cache::get($lastKey);

        if (is_array($last) && is_array($last['data'] ?? null) && is_string($last['at'] ?? null)) {
            return new Result($last['data'], stale: true, cached: true, lastUpdated: $last['at']);
        }

        return new Result(null, stale: true, cached: false, lastUpdated: null);
    }

    /**
     * Build a cache key that is unique per resource + query params.
     *
     * @param  array<string, mixed>  $query
     */
    private function cacheKey(string $key, array $query): string
    {
        if ($query === []) {
            return $key;
        }

        ksort($query);

        return $key.':'.md5((string) json_encode($query));
    }
}
