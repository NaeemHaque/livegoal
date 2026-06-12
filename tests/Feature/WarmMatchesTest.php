<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Covers app:warm-matches — the scheduled warmer that keeps the featured
 * competitions' match feeds hot so GET /matches/upcoming and /matches/day serve
 * from cache instead of a slow on-demand upstream aggregation.
 *
 * No RefreshDatabase: the command touches only config, the cache (array store per
 * phpunit.xml) and the (faked) HTTP client — never the database.
 */
class WarmMatchesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_it_warms_cold_match_feeds_up_to_the_per_run_cap(): void
    {
        Http::fake(['api.football-data.org/*' => Http::response(['matches' => []])]);

        $this->artisan('app:warm-matches')->assertSuccessful();

        // All eight featured feeds are cold, but a run is capped at four fetches.
        Http::assertSentCount(4);
    }

    public function test_it_skips_feeds_that_are_still_fresh(): void
    {
        // Pre-warm six of the eight featured feeds; only two remain cold.
        foreach (['WC', 'CL', 'PL', 'PD', 'SA', 'BL1'] as $code) {
            Cache::put("fd:competition:{$code}:matches", [
                'data' => ['matches' => []],
                'at' => Date::now()->toIso8601String(),
            ], 600);
        }

        Http::fake(['api.football-data.org/*' => Http::response(['matches' => []])]);

        $this->artisan('app:warm-matches')->assertSuccessful();

        // Only the two cold feeds are fetched (under the cap), the fresh ones skipped.
        Http::assertSentCount(2);
    }

    public function test_it_is_scheduled_every_minute_without_overlapping(): void
    {
        $schedule = app(Schedule::class);

        $event = collect($schedule->events())
            ->first(fn ($event): bool => str_contains((string) $event->command, 'app:warm-matches'));

        $this->assertNotNull($event, 'app:warm-matches should be scheduled.');
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }
}
