<?php

use App\Http\Controllers\Api\CompetitionController;
use App\Http\Controllers\Api\LiveController;
use App\Http\Controllers\Api\MatchController;
use App\Http\Controllers\Api\PersonController;
use App\Http\Controllers\Api\TeamController;
use Illuminate\Support\Facades\Route;

// Each route's cache.api:{seconds} window is matched to the data's volatility
// (see config/football.php `ttl`): live scores refresh often, reference data rarely.

Route::get('live', [LiveController::class, 'index'])->middleware('cache.api:5');

Route::get('competitions', [CompetitionController::class, 'index'])->middleware('cache.api:3600');
Route::get('competitions/{id}', [CompetitionController::class, 'show'])->middleware('cache.api:3600');
Route::get('competitions/{id}/standings', [CompetitionController::class, 'standings'])->middleware('cache.api:120');
Route::get('competitions/{id}/matches', [CompetitionController::class, 'matches'])->middleware('cache.api:120');
Route::get('competitions/{id}/teams', [CompetitionController::class, 'teams'])->middleware('cache.api:3600');
Route::get('competitions/{id}/scorers', [CompetitionController::class, 'scorers'])->middleware('cache.api:300');

Route::get('matches', [MatchController::class, 'index'])->middleware('cache.api:120');
Route::get('matches/day', [MatchController::class, 'day'])->middleware('cache.api:120');
Route::get('matches/upcoming', [MatchController::class, 'upcoming'])->middleware('cache.api:120');
Route::get('matches/{id}', [MatchController::class, 'show'])->middleware('cache.api:30');

Route::get('teams/{id}', [TeamController::class, 'show'])->middleware('cache.api:3600');
Route::get('teams/{id}/matches', [TeamController::class, 'matches'])->middleware('cache.api:120');

Route::get('persons/{id}', [PersonController::class, 'show'])->middleware('cache.api:3600');
