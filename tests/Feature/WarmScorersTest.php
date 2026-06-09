<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Covers app:warm-scorers — the scheduled cache-warmer that keeps the featured
 * leagues' Golden Boot feed hot so the Top Scorers tabs are instant cache hits.
 *
 * No RefreshDatabase: the command touches only config, the cache (array store
 * per phpunit.xml) and the (faked) HTTP client — never the database.
 */
class WarmScorersTest extends TestCase
{
    /** Featured competitions whose `kind` is `league` (see config/football.php). */
    private const LEAGUES = ['PL', 'PD', 'SA', 'BL1', 'FL1', 'BSA'];

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_it_warms_the_scorers_cache_for_every_featured_league(): void
    {
        Http::fake(['api.football-data.org/*' => Http::response(['scorers' => []])]);

        $this->artisan('app:warm-scorers')->assertSuccessful();

        foreach (self::LEAGUES as $code) {
            $this->assertTrue(
                Cache::has("fd:competition:{$code}:scorers"),
                "Expected the scorers cache to be warmed for {$code}.",
            );
        }
    }

    public function test_it_does_not_warm_cups_only_leagues(): void
    {
        Http::fake(['api.football-data.org/*' => Http::response(['scorers' => []])]);

        $this->artisan('app:warm-scorers')->assertSuccessful();

        // World Cup / Champions League are featured but are cups — never warmed here.
        $this->assertFalse(Cache::has('fd:competition:WC:scorers'));
        $this->assertFalse(Cache::has('fd:competition:CL:scorers'));
    }

    public function test_it_skips_the_upstream_call_when_a_feed_is_already_fresh(): void
    {
        // Pre-seed PL's fresh key in the shape FootballData::cached() expects.
        Cache::put('fd:competition:PL:scorers', [
            'data' => ['scorers' => []],
            'at' => Date::now()->toIso8601String(),
        ], 1800);

        Http::fake(['api.football-data.org/*' => Http::response(['scorers' => []])]);

        $this->artisan('app:warm-scorers')->assertSuccessful();

        // PL was fresh, so no PL request goes out; the other leagues still fetch.
        Http::assertNotSent(
            fn ($request): bool => str_contains($request->url(), '/competitions/PL/scorers'),
        );
    }

    public function test_it_is_scheduled_every_ten_minutes_without_overlapping(): void
    {
        $schedule = app(Schedule::class);

        $event = collect($schedule->events())
            ->first(fn ($event): bool => str_contains((string) $event->command, 'app:warm-scorers'));

        $this->assertNotNull($event, 'app:warm-scorers should be scheduled.');
        $this->assertSame('*/10 * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }
}
