<?php

use App\Http\Controllers\Api\CompetitionController;
use App\Http\Controllers\Api\MatchController;
use App\Http\Controllers\Api\PersonController;
use App\Http\Controllers\Api\TeamController;
use Illuminate\Support\Facades\Route;

Route::get('competitions', [CompetitionController::class, 'index']);
Route::get('competitions/{id}', [CompetitionController::class, 'show']);
Route::get('competitions/{id}/standings', [CompetitionController::class, 'standings']);
Route::get('competitions/{id}/matches', [CompetitionController::class, 'matches']);
Route::get('competitions/{id}/teams', [CompetitionController::class, 'teams']);
Route::get('competitions/{id}/scorers', [CompetitionController::class, 'scorers']);

Route::get('matches', [MatchController::class, 'index']);
Route::get('matches/{id}', [MatchController::class, 'show']);

Route::get('teams/{id}', [TeamController::class, 'show']);
Route::get('teams/{id}/matches', [TeamController::class, 'matches']);

Route::get('persons/{id}', [PersonController::class, 'show']);
