<?php

use App\Http\Controllers\ContentController;
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
Route::get('/matches/{date}', [SeoShellController::class, 'matchesByDate'])
    ->where('date', '\d{4}-\d{2}-\d{2}')
    ->name('matches.date');
Route::get('/match/{id}', [SeoShellController::class, 'match'])->name('match');
Route::get('/competitions', [SeoShellController::class, 'competitions'])->name('competitions');
Route::get('/competition/{id}', [SeoShellController::class, 'competition'])->name('competition');
Route::get('/team/{id}', [SeoShellController::class, 'team'])->name('team');
Route::get('/player/{id}', [SeoShellController::class, 'player'])->name('player');
Route::get('/scorers', [SeoShellController::class, 'scorers'])->name('scorers');
Route::get('/favorites', [SeoShellController::class, 'favorites'])->name('favorites');
Route::get('/search', [SeoShellController::class, 'search'])->name('search');
Route::get('/settings', [SeoShellController::class, 'settings'])->name('settings');

// Server-rendered editorial content (guides/explainers/trust) — see
// config/guides.php. Standalone HTML pages (not the SPA), the AEO/GEO layer.
Route::get('/guides', [ContentController::class, 'index'])->name('guides');
Route::get('/guides/how-to-watch-world-cup-2026-free/{country}', [ContentController::class, 'watch'])->name('guides.watch');
Route::get('/guides/{slug}', [ContentController::class, 'show'])->name('guides.show');
Route::get('/about', [ContentController::class, 'show'])->defaults('slug', 'about')->name('about');
Route::get('/how-our-data-works', [ContentController::class, 'show'])->defaults('slug', 'how-our-data-works')->name('data');
Route::get('/contact', [ContentController::class, 'show'])->defaults('slug', 'contact')->name('contact');

// Unknown paths: render the shell (so the SPA's NotFound page shows on a direct
// hit) but with a real 404 status, keeping junk URLs out of the index.
Route::fallback([SeoShellController::class, 'notFound'])->name('spa');
