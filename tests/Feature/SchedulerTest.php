<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * Covers the cron-less scheduler trigger and the scheduled poller registration
 * (Phase 3, commit 3).
 *
 * Two surfaces are exercised:
 *
 * 1. GET /scheduler/run (SchedulerController@run) — a token-guarded fallback that
 *    lets an external pinger drive `schedule:run` on hosts without system cron.
 *    The token is compared with hash_equals against config('football.scheduler_token');
 *    an empty configured token OR a mismatch aborts 404. A match runs schedule:run
 *    and returns 200 "OK".
 *
 * 2. routes/console.php — registers the single site-wide poller
 *    `app:poll-live-scores` on the scheduler, every minute, without overlapping.
 *
 * No RefreshDatabase: these paths touch only config, the cache (array store per
 * phpunit.xml), the HTTP client and the scheduler — never the database.
 */
class SchedulerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Never hit the wire; never really sleep if the poller's retries fire.
        Http::preventStrayRequests();
        Sleep::fake();
    }

    // --- 1. no token configured: route is disabled --------------------------

    public function test_it_aborts_404_when_no_token_is_configured_and_none_provided(): void
    {
        Config::set('football.scheduler_token', '');

        $this->get('/scheduler/run')->assertNotFound();
    }

    public function test_it_aborts_404_when_no_token_is_configured_even_if_one_is_provided(): void
    {
        Config::set('football.scheduler_token', '');

        $this->get('/scheduler/run?token=anything')->assertNotFound();
    }

    // --- 2. wrong token -----------------------------------------------------

    public function test_it_aborts_404_when_the_provided_token_does_not_match(): void
    {
        Config::set('football.scheduler_token', 'secret');

        $this->get('/scheduler/run?token=nope')->assertNotFound();
    }

    public function test_it_aborts_404_when_the_token_is_missing_but_one_is_configured(): void
    {
        Config::set('football.scheduler_token', 'secret');

        $this->get('/scheduler/run')->assertNotFound();
    }

    // --- 3. correct token: runs schedule:run, returns 200 OK ----------------

    public function test_it_runs_the_scheduler_and_returns_ok_when_the_token_matches(): void
    {
        Config::set('football.scheduler_token', 'secret');

        // schedule:run dispatches each due event in a *separate* sub-process
        // (`'artisan' app:poll-live-scores` with output to /dev/null), not
        // in-process. That child boots its own app with its own array cache and
        // its own (unfaked) HTTP client, so neither this process's Http::fake()
        // nor Cache reflects what the child did — they are unobservable here.
        // The controller's contract is the guard + invocation + response, so we
        // assert exactly that: the request is authorized, schedule:run runs
        // without throwing, and the body is "OK".
        $response = $this->get('/scheduler/run?token=secret');

        $response->assertOk();
        $this->assertSame('OK', $response->getContent());
    }

    public function test_token_comparison_is_an_exact_match_not_a_prefix(): void
    {
        Config::set('football.scheduler_token', 'secret');

        // A prefix of the real token must not pass (hash_equals, not str_starts_with).
        $this->get('/scheduler/run?token=secre')->assertNotFound();

        // A superset of the real token must not pass either.
        $this->get('/scheduler/run?token=secretX')->assertNotFound();
    }

    // --- 3b. the public route is rate-limited -------------------------------

    public function test_it_rate_limits_excessive_requests(): void
    {
        Config::set('football.scheduler_token', 'secret');

        // The throttle (20/min) runs before the controller, so even rejected
        // (wrong-token → 404) requests count toward the limit.
        for ($i = 0; $i < 20; $i++) {
            $this->get('/scheduler/run?token=nope')->assertNotFound();
        }

        $this->get('/scheduler/run?token=nope')->assertStatus(429);
    }

    // --- 4. schedule registration -------------------------------------------

    public function test_it_registers_the_live_poller_to_run_every_minute(): void
    {
        $schedule = app(Schedule::class);

        $events = collect($schedule->events())
            ->filter(fn ($event): bool => str_contains((string) $event->command, 'app:poll-live-scores'));

        $this->assertCount(1, $events, 'app:poll-live-scores should be scheduled exactly once.');

        $event = $events->first();

        // Every minute.
        $this->assertSame('* * * * *', $event->expression);
    }

    public function test_the_live_poller_is_scheduled_without_overlapping(): void
    {
        $schedule = app(Schedule::class);

        $event = collect($schedule->events())
            ->first(fn ($event): bool => str_contains((string) $event->command, 'app:poll-live-scores'));

        $this->assertNotNull($event, 'app:poll-live-scores should be scheduled.');

        // withoutOverlapping() installs a mutex on the event.
        $this->assertTrue($event->withoutOverlapping);
    }
}
