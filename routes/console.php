<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// The single site-wide live poller (see docs/LIVE_POLLING.md).
Schedule::command('app:poll-live-scores')
    ->everyMinute()
    ->withoutOverlapping();

// Keep the featured leagues' Golden Boot feed hot so the Top Scorers tabs are
// instant cache hits instead of slow on-demand football-data.org calls. cached()
// no-ops while fresh (30m TTL), so this only refetches a handful of feeds twice
// an hour — well inside the free-tier rate limit.
Schedule::command('app:warm-scorers')
    ->everyTenMinutes()
    ->withoutOverlapping();

// Keep the featured match feeds warm so /matches/upcoming and /matches/day serve
// from cache instead of fanning out to the rate-limited upstream on a reload — a
// cold aggregation can otherwise exceed the browser timeout and blank the page.
// The command self-paces to a few feeds per run, so every minute is safe.
Schedule::command('app:warm-matches')
    ->everyMinute()
    ->withoutOverlapping();
