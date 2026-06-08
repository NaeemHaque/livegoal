<?php

namespace Tests\Feature\Services;

use App\Services\Football\FootballData;
use App\Services\Football\Result;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * Covers the upstream client contract for football-data.org: authenticated GET
 * with retry/backoff on 429 + connection failures, error swallowing (never
 * throws), and cache-served reads with stale-on-failure last-known-good fallback.
 *
 * Note: no RefreshDatabase — this service only talks to the cache (array store
 * per phpunit.xml) and the HTTP client, never the database.
 */
class FootballDataTest extends TestCase
{
    private FootballData $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Deterministic token + fast retries; never hit the wire.
        Config::set('football.token', 'test-token');
        Config::set('football.base_url', 'https://api.football-data.org/v4');

        Http::preventStrayRequests();
        Sleep::fake();

        $this->service = new FootballData;
    }

    // --- 1. Happy path: get() decodes + sends the auth header -----------------

    public function test_get_returns_decoded_array_and_sends_auth_token_header(): void
    {
        Http::fake([
            '*' => Http::response(['competitions' => [['id' => 2021]]], 200),
        ]);

        $data = $this->service->get('competitions');

        $this->assertSame(['competitions' => [['id' => 2021]]], $data);

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('X-Auth-Token', 'test-token')
            && str_contains($request->url(), 'api.football-data.org/v4/competitions'));
    }

    // --- 2. cached(): repeated calls hit cache, upstream once ----------------

    public function test_cached_serves_from_cache_on_repeated_calls(): void
    {
        Http::fake([
            '*' => Http::response(['standings' => ['table' => [1, 2, 3]]], 200),
        ]);

        $first = $this->service->cached('standings:PL', 600, 'competitions/PL/standings');
        $second = $this->service->cached('standings:PL', 600, 'competitions/PL/standings');

        Http::assertSentCount(1);

        $this->assertEquals($first->data, $second->data);
        $this->assertSame(['standings' => ['table' => [1, 2, 3]]], $second->data);

        // First call was a fresh fill; second was served from cache.
        $this->assertFalse($first->cached);
        $this->assertFalse($first->stale);
        $this->assertTrue($second->cached);
        $this->assertFalse($second->stale);
    }

    // --- 3. 429 then last-good fallback -------------------------------------

    public function test_cached_falls_back_to_last_good_as_stale_on_upstream_failure(): void
    {
        // Http::fake cannot be re-stubbed mid-test, so a single sequence drives
        // both phases. With the default retries=2 a persistent 429 makes 3 upstream
        // attempts before the retry helper throws, so the failure phase below pushes
        // three 429s (retries + 1).
        Http::fake([
            '*' => Http::sequence()
                ->push(['scorers' => ['Haaland']], 200) // 1st cached(): fresh fill
                ->push(['error' => 'rate limited'], 429) // 2nd cached(): 3 attempts...
                ->push(['error' => 'rate limited'], 429)
                ->push(['error' => 'rate limited'], 429)
                ->whenEmpty(Http::response(['unexpected' => true], 500)),
        ]);

        $primed = $this->service->cached('scorers:PL', 1800, 'competitions/PL/scorers');
        $this->assertFalse($primed->stale);

        // Expire ONLY the fresh key; keep last-known-good intact.
        Cache::forget('fd:scorers:PL');

        $result = $this->service->cached('scorers:PL', 1800, 'competitions/PL/scorers');

        $this->assertInstanceOf(Result::class, $result);
        $this->assertSame(['scorers' => ['Haaland']], $result->data);
        $this->assertTrue($result->stale);
        $this->assertTrue($result->cached);
        $this->assertNotNull($result->lastUpdated);

        // A failure must NOT be written under the fresh key.
        $this->assertNull(Cache::get('fd:scorers:PL'));
    }

    // --- 4. 429 with no last-good = hard miss, nothing pinned ----------------

    public function test_cached_hard_miss_returns_null_and_does_not_pin_failure(): void
    {
        // 1st cached(): 429 hard miss (no last-good) — 3 attempts (retries + 1).
        // 2nd cached(): a fresh 200.
        Http::fake([
            '*' => Http::sequence()
                ->push(['error' => 'rate limited'], 429)
                ->push(['error' => 'rate limited'], 429)
                ->push(['error' => 'rate limited'], 429)
                ->push(['matches' => [['id' => 9]]], 200)
                ->whenEmpty(Http::response(['unexpected' => true], 500)),
        ]);

        $miss = $this->service->cached('matches:PL', 600, 'competitions/PL/matches');

        $this->assertNull($miss->data);
        $this->assertTrue($miss->stale);
        $this->assertFalse($miss->cached);
        $this->assertNull($miss->lastUpdated);

        // Nothing was stored under the fresh key, so the next request retries upstream.
        $this->assertNull(Cache::get('fd:matches:PL'));

        $retry = $this->service->cached('matches:PL', 600, 'competitions/PL/matches');

        $this->assertSame(['matches' => [['id' => 9]]], $retry->data);
        $this->assertFalse($retry->stale);
        $this->assertFalse($retry->cached);
    }

    // --- 5. Retry exhaustion: retries + 1 attempts on 429 -------------------

    public function test_get_retries_429_until_exhausted(): void
    {
        Config::set('football.retries', 2); // => 3 total attempts

        Http::fake([
            '*' => Http::response(['error' => 'rate limited'], 429),
        ]);

        $result = $this->service->get('competitions/PL/standings');

        $this->assertNull($result);
        Http::assertSentCount(3);
        Sleep::assertSleptTimes(2); // one backoff between each of the 3 attempts
    }

    // --- 6. Non-retryable status (403) does not retry -----------------------

    public function test_get_does_not_retry_non_retryable_status_and_logs_status(): void
    {
        Log::spy();

        Http::fake([
            '*' => Http::response(['error' => 'forbidden'], 403),
        ]);

        $result = $this->service->get('competitions/PL/standings');

        $this->assertNull($result);
        Http::assertSentCount(1); // exactly one attempt, no retry
        Sleep::assertNeverSlept();

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'football-data request failed'
                && ($context['status'] ?? null) === 403);
    }

    // --- 7. Connection failure is retried then swallowed --------------------

    public function test_get_retries_connection_failure_then_returns_null(): void
    {
        Config::set('football.retries', 2); // => 3 total attempts

        Log::spy();

        Http::fake([
            '*' => Http::failedConnection(),
        ]);

        $result = $this->service->get('competitions');

        $this->assertNull($result);
        Http::assertSentCount(3);
        Sleep::assertSleptTimes(2);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message): bool => $message === 'football-data connection failed');
    }

    // --- 8. cached() success populates the last-good key ---------------------

    public function test_cached_success_populates_last_good_used_on_later_failure(): void
    {
        // 1st cached() fills last-good; 2nd cached() hits a connection failure that
        // is retried 3 times (retries + 1) before being swallowed.
        Http::fake([
            '*' => Http::sequence()
                ->push(['team' => ['id' => 57, 'name' => 'Arsenal']], 200)
                ->pushFailedConnection()
                ->pushFailedConnection()
                ->pushFailedConnection()
                ->whenEmpty(Http::response(['unexpected' => true], 500)),
        ]);

        $this->service->cached('team:57', 86400, 'teams/57');

        // Expire the fresh key so the next call must go upstream.
        Cache::forget('fd:team:57');

        $result = $this->service->cached('team:57', 86400, 'teams/57');

        $this->assertSame(['team' => ['id' => 57, 'name' => 'Arsenal']], $result->data);
        $this->assertTrue($result->stale);
        $this->assertTrue($result->cached);
    }

    // --- 9. Query-param keying does not collide -----------------------------

    public function test_cached_keys_are_distinct_per_query_params(): void
    {
        Http::fake([
            '*' => function (Request $request) {
                $matchday = $request->data()['matchday'] ?? null;

                return Http::response(['matchday' => $matchday, 'matches' => ["md{$matchday}"]], 200);
            },
        ]);

        $one = $this->service->cached('matches:PL', 600, 'competitions/PL/matches', ['matchday' => 1]);
        $two = $this->service->cached('matches:PL', 600, 'competitions/PL/matches', ['matchday' => 2]);

        // Two distinct upstream calls — no cache collision between the two queries.
        Http::assertSentCount(2);

        $this->assertSame(['matchday' => 1, 'matches' => ['md1']], $one->data);
        $this->assertSame(['matchday' => 2, 'matches' => ['md2']], $two->data);
        $this->assertNotEquals($one->data, $two->data);

        // Re-requesting matchday=1 is served from its own cache slot (no new call).
        $oneAgain = $this->service->cached('matches:PL', 600, 'competitions/PL/matches', ['matchday' => 1]);
        Http::assertSentCount(2);
        $this->assertTrue($oneAgain->cached);
        $this->assertSame($one->data, $oneAgain->data);
    }

    // --- 10. No secret leakage in failure-path logs -------------------------

    public function test_failure_log_context_never_contains_the_token(): void
    {
        Log::spy();

        Http::fake([
            '*' => Http::response(['error' => 'forbidden'], 403),
        ]);

        $this->service->get('competitions/PL/standings');

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context): bool {
                $serialized = (string) json_encode($context);

                return ! str_contains($serialized, 'test-token');
            });
    }
}
