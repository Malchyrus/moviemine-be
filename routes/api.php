<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AutomationsController;
use App\Http\Controllers\Api\CustomListController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\TmdbController;
use App\Http\Controllers\Api\WatchlistController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['ok' => true]));

// Auth
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// TMDB proxy
Route::prefix('tmdb')->group(function () {
    Route::get('/trending', [TmdbController::class, 'trending']);
    Route::get('/popular', [TmdbController::class, 'popular']);
    Route::get('/upcoming', [TmdbController::class, 'upcoming']);
    Route::get('/top-rated', [TmdbController::class, 'topRated']);
    Route::get('/genres', [TmdbController::class, 'genres']);
    Route::get('/search', [TmdbController::class, 'search']);
    Route::get('/movie/{id}', [TmdbController::class, 'show'])->whereNumber('id');
    Route::get('/movie/{id}/watch-providers', [TmdbController::class, 'watchProviders'])->whereNumber('id');
    Route::get('/watch-providers/regions', [TmdbController::class, 'watchRegions']);
});

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::patch('/auth/me', [AuthController::class, 'updateMe']);

    // Library / watchlist
    Route::get('/movies', [WatchlistController::class, 'index']);
    Route::post('/movies', [WatchlistController::class, 'store']);
    Route::patch('/movies/{tmdbId}', [WatchlistController::class, 'update'])->whereNumber('tmdbId');
    Route::delete('/movies/{tmdbId}', [WatchlistController::class, 'destroy'])->whereNumber('tmdbId');

    // Reviews
    Route::get('/reviews', [ReviewController::class, 'index']);
    Route::post('/reviews', [ReviewController::class, 'store']);
    Route::delete('/reviews/{tmdbId}', [ReviewController::class, 'destroy'])->whereNumber('tmdbId');

    // Custom lists
    Route::get('/lists', [CustomListController::class, 'index']);
    Route::post('/lists', [CustomListController::class, 'store']);
    Route::patch('/lists/{id}', [CustomListController::class, 'update'])->whereNumber('id');
    Route::delete('/lists/{id}', [CustomListController::class, 'destroy'])->whereNumber('id');
    Route::post('/lists/{listId}/movies', [CustomListController::class, 'addMovie'])->whereNumber('listId');
    Route::delete('/lists/{listId}/movies/{tmdbId}', [CustomListController::class, 'removeMovie'])->whereNumber('listId', 'tmdbId');
    Route::post('/lists/{listId}/move', [CustomListController::class, 'move'])->whereNumber('listId');

    // Automations
    Route::get('/automations', [AutomationsController::class, 'index']);
    Route::post('/automations', [AutomationsController::class, 'store']);
    Route::patch('/automations/{id}', [AutomationsController::class, 'update'])->whereNumber('id');
    Route::delete('/automations/{id}', [AutomationsController::class, 'destroy'])->whereNumber('id');
});
