<?php

use App\Http\Controllers\Api\CompetitionController;
use Illuminate\Support\Facades\Route;

Route::get('competitions', [CompetitionController::class, 'index']);
Route::get('competitions/{id}', [CompetitionController::class, 'show']);
