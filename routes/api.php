<?php

use App\Http\Controllers\Api\CompetitionController;
use App\Http\Controllers\Api\MatchController;
use Illuminate\Support\Facades\Route;

Route::get('competitions', [CompetitionController::class, 'index']);
Route::get('competitions/{id}', [CompetitionController::class, 'show']);
Route::get('competitions/{id}/standings', [CompetitionController::class, 'standings']);
Route::get('competitions/{id}/matches', [CompetitionController::class, 'matches']);

Route::get('matches', [MatchController::class, 'index']);
Route::get('matches/{id}', [MatchController::class, 'show']);
