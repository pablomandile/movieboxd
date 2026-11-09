<?php

use App\Http\Controllers\Api\V1\MatchController;
use App\Http\Controllers\Api\V1\WatchlistController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
 * API para apps externas (p. ej. Hoy Trasnoche manda a la watchlist la película
 * de cada episodio). Se autentica con un token personal que el usuario crea en
 * Ajustes → API, con la habilidad "watchlist".
 */
Route::prefix('v1')
    ->middleware(['auth:sanctum', 'abilities:watchlist', 'throttle:30,1'])
    ->group(function () {
        Route::get('me', fn (Request $request) => $request->user()->only('username', 'name'))->name('api.me');
        Route::get('match', MatchController::class)->name('api.match');
        Route::get('watchlist/status', [WatchlistController::class, 'status'])->name('api.watchlist.status');
        Route::post('watchlist', [WatchlistController::class, 'store'])->name('api.watchlist.store');
        Route::delete('watchlist/{tmdbId}', [WatchlistController::class, 'destroy'])
            ->whereNumber('tmdbId')
            ->name('api.watchlist.destroy');
    });
