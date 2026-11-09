<?php

use App\Http\Controllers\Settings\ApiTokenController;
use App\Http\Controllers\Settings\ImportController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/api', [ApiTokenController::class, 'index'])->name('api-tokens.index');
    Route::post('settings/api', [ApiTokenController::class, 'store'])->name('api-tokens.store');
    Route::delete('settings/api/{token}', [ApiTokenController::class, 'destroy'])->whereNumber('token')->name('api-tokens.destroy');

    Route::get('settings/import', [ImportController::class, 'show'])->name('import.show');
    Route::post('settings/import', [ImportController::class, 'store'])->name('import.store');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/Appearance');
    })->name('appearance');
});
