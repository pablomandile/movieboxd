<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DiaryEntryController;
use App\Http\Controllers\EpisodeController;
use App\Http\Controllers\EpisodeWatchController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ListCollaboratorController;
use App\Http\Controllers\ListController;
use App\Http\Controllers\ListItemController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SeasonController;
use App\Http\Controllers\SeasonWatchController;
use App\Http\Controllers\Settings\LocaleController;
use App\Http\Controllers\TitleController;
use App\Http\Controllers\TitleLikeController;
use App\Http\Controllers\TitleResolverController;
use App\Http\Controllers\WatchedTitleController;
use App\Http\Controllers\WatchlistController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('about', AboutController::class)->name('about');

// Catálogo (público)
Route::get('search', SearchController::class)->middleware('throttle:30,1')->name('search');
Route::get('resolve/{type}/{tmdbId}', TitleResolverController::class)
    ->whereIn('type', ['movie', 'tv'])
    ->whereNumber('tmdbId')
    ->middleware('throttle:30,1')
    ->name('titles.resolve');
Route::get('film/{title:slug}', [TitleController::class, 'film'])->name('films.show');
Route::get('show/{title:slug}', [TitleController::class, 'show'])->name('shows.show');
Route::get('show/{title:slug}/season/{seasonNumber}', [SeasonController::class, 'show'])
    ->whereNumber('seasonNumber')
    ->name('seasons.show');
Route::get('show/{title:slug}/season/{seasonNumber}/episode/{episodeNumber}', [EpisodeController::class, 'show'])
    ->whereNumber('seasonNumber')
    ->whereNumber('episodeNumber')
    ->name('episodes.show');
Route::get('review/{review}', [ReviewController::class, 'show'])->name('reviews.show');
Route::get('u/{user:username}', [ProfileController::class, 'show'])->name('profiles.show');
Route::get('u/{user:username}/{tab}', [ProfileController::class, 'tab'])
    ->whereIn('tab', ProfileController::TABS)
    ->name('profiles.tab');
Route::get('lists', [ListController::class, 'index'])->name('lists.index');
Route::get('list/{username}/{list}', [ListController::class, 'show'])->name('lists.show');

// El starter kit mandaba acá tras el login y mostraba una pantalla de andamiaje vacía.
// La pantalla útil es la home (feed + tendencias); se mantiene la ruta por si quedó
// algún enlace guardado.
Route::redirect('dashboard', '/')->name('dashboard');

// Trackeo (requiere sesión)
Route::middleware(['auth', 'throttle:60,1'])->group(function () {
    Route::post('titles/{title}/watched', [WatchedTitleController::class, 'toggle'])->name('titles.watched');
    Route::post('titles/{title}/like', [TitleLikeController::class, 'toggle'])->name('titles.like');
    Route::post('titles/{title}/watchlist', [WatchlistController::class, 'toggle'])->name('titles.watchlist');
    Route::put('ratings', [RatingController::class, 'upsert'])->name('ratings.upsert');
    Route::post('log', [DiaryEntryController::class, 'store'])->name('diary.store');
    Route::put('diary/{entry}', [DiaryEntryController::class, 'update'])->name('diary.update');
    Route::delete('diary/{entry}', [DiaryEntryController::class, 'destroy'])->name('diary.destroy');
    Route::post('episodes/{episode}/watch', [EpisodeWatchController::class, 'toggle'])->name('episodes.watch');
    Route::post('seasons/{season}/watch-all', [SeasonWatchController::class, 'store'])->name('seasons.watchAll');
    Route::delete('seasons/{season}/watch-all', [SeasonWatchController::class, 'destroy'])->name('seasons.unwatchAll');

    Route::post('reviews', [ReviewController::class, 'store'])->middleware('feature:reviews')->name('reviews.store');
    Route::put('reviews/{review}', [ReviewController::class, 'update'])->name('reviews.update');
    Route::delete('reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');
    Route::post('reviews/{review}/like', [ReviewController::class, 'toggleLike'])->name('reviews.like');
    Route::post('reviews/{review}/comments', [CommentController::class, 'store'])
        ->middleware('feature:comments')
        ->name('comments.store');
    Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');
    Route::post('reports', [ReportController::class, 'store'])->name('reports.store');

    Route::post('follow/{user:username}', [FollowController::class, 'toggle'])->name('follows.toggle');
    Route::post('titles/{title}/favorite', [FavoriteController::class, 'toggle'])->name('titles.favorite');

    Route::middleware('feature:lists')->group(function () {
        Route::get('lists/create', [ListController::class, 'create'])->name('lists.create');
        Route::post('lists', [ListController::class, 'store'])->name('lists.store');
        Route::get('lists/{list}/edit', [ListController::class, 'edit'])->name('lists.edit');
        Route::put('lists/{list}', [ListController::class, 'update'])->name('lists.update');
        Route::delete('lists/{list}', [ListController::class, 'destroy'])->name('lists.destroy');
        Route::post('lists/{list}/items', [ListItemController::class, 'store'])->name('lists.items.store');
        Route::put('lists/{list}/items/{item}', [ListItemController::class, 'update'])->name('lists.items.update');
        Route::delete('lists/{list}/items/{item}', [ListItemController::class, 'destroy'])->name('lists.items.destroy');
        Route::post('lists/{list}/reorder', [ListItemController::class, 'reorder'])->name('lists.reorder');

        // Colaboración por enlace de invitación
        Route::post('lists/{list}/invite', [ListCollaboratorController::class, 'invite'])->name('lists.invite');
        Route::post('lists/{list}/invite/regenerate', [ListCollaboratorController::class, 'regenerate'])
            ->name('lists.invite.regenerate');
        Route::delete('lists/{list}/collaborators/{user}', [ListCollaboratorController::class, 'revoke'])
            ->name('lists.collaborators.revoke');
        // OJO: no usar 'list/...' — colisiona con list/{username}/{list} (lists.show),
        // que matchearía primero tomando 'invite' como username.
        // Sumarse requiere sesión: 'auth' del grupo padre manda a login y vuelve acá.
        Route::get('lists/join/{token}', [ListCollaboratorController::class, 'accept'])
            ->name('lists.invite.accept');
    });
    Route::post('lists/{list}/like', [ListController::class, 'toggleLike'])->name('lists.like');
    Route::post('lists/{list}/comments', [CommentController::class, 'storeForList'])
        ->middleware('feature:comments')
        ->name('lists.comments.store');
});

Route::middleware('auth')->group(function () {
    Route::get('diary', [DiaryEntryController::class, 'index'])->name('diary.index');
    Route::get('watchlist', [WatchlistController::class, 'index'])->name('watchlist.index');
});

Route::put('settings/locale', [LocaleController::class, 'update'])->name('locale.update');

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');

    // {user:id}: el binding por defecto del modelo es username
    Route::get('users', [Admin\UserController::class, 'index'])->name('users.index');
    Route::put('users/{user:id}/role', [Admin\UserController::class, 'updateRole'])->name('users.role');
    Route::put('users/{user:id}/ban', [Admin\UserController::class, 'toggleBan'])->name('users.ban');

    Route::get('reports', [Admin\ReportController::class, 'index'])->name('reports.index');
    Route::put('reports/{report}', [Admin\ReportController::class, 'update'])->name('reports.update');

    Route::get('settings', [Admin\SettingController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [Admin\SettingController::class, 'update'])->name('settings.update');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
