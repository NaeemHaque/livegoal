<?php

use App\Http\Controllers\SchedulerController;
use Illuminate\Support\Facades\Route;

// Cron-less scheduler trigger (token-guarded + rate-limited). The legitimate
// pinger hits this once a minute; the throttle caps abuse of the public route.
// See docs/LIVE_POLLING.md.
Route::get('scheduler/run', [SchedulerController::class, 'run'])->middleware('throttle:20,1');

Route::fallback(fn () => view('app'))->name('spa');
