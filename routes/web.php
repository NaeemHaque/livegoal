<?php

use App\Http\Controllers\SchedulerController;
use App\Http\Controllers\SeoShellController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

// Cron-less scheduler trigger (token-guarded + rate-limited). The legitimate
// pinger hits this once a minute; the throttle caps abuse of the public route.
// See docs/LIVE_POLLING.md.
Route::get('scheduler/run', [SchedulerController::class, 'run'])->middleware('throttle:20,1');

// Crawl-control surface (dynamic so URLs are environment-correct).
Route::get('robots.txt', [SitemapController::class, 'robots']);
Route::get('sitemap.xml', [SitemapController::class, 'index']);

// SPA routes. Each renders the Vue shell with per-URL SEO metadata resolved from
// cached football data (see App\Http\Controllers\SeoShellController). Listing the
// real path shapes explicitly lets the fallback below return true 404s for
// everything else, instead of soft-404ing every URL with a 200.
Route::get('/', [SeoShellController::class, 'home'])->name('home');
Route::get('/matches', [SeoShellController::class, 'matches'])->name('matches');
Route::get('/match/{id}', [SeoShellController::class, 'match'])->name('match');
Route::get('/competitions', [SeoShellController::class, 'competitions'])->name('competitions');
Route::get('/competition/{id}', [SeoShellController::class, 'competition'])->name('competition');
Route::get('/team/{id}', [SeoShellController::class, 'team'])->name('team');
Route::get('/player/{id}', [SeoShellController::class, 'player'])->name('player');
Route::get('/scorers', [SeoShellController::class, 'scorers'])->name('scorers');
Route::get('/favorites', [SeoShellController::class, 'favorites'])->name('favorites');
Route::get('/search', [SeoShellController::class, 'search'])->name('search');
Route::get('/settings', [SeoShellController::class, 'settings'])->name('settings');

// Unknown paths: render the shell (so the SPA's NotFound page shows on a direct
// hit) but with a real 404 status, keeping junk URLs out of the index.
Route::fallback([SeoShellController::class, 'notFound'])->name('spa');
