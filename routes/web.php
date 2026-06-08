<?php

use App\Http\Controllers\SchedulerController;
use Illuminate\Support\Facades\Route;

// Cron-less scheduler trigger (token-guarded). See docs/LIVE_POLLING.md.
Route::get('scheduler/run', [SchedulerController::class, 'run']);

Route::fallback(fn () => view('app'))->name('spa');
