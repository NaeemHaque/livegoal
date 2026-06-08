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
