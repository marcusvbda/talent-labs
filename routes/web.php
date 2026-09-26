<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\ConnectedIntegrationOAuthController;
use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'landing')->name('home');

Route::inertia('/dashboard', 'dashboard')->middleware(['auth', 'client'])->name('dashboard');

Route::put('/locale', [LocaleController::class, 'update'])->name('locale.update');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('login.store');
});

Route::post('/logout', LogoutController::class)->middleware('auth')->name('logout');

Route::middleware(['web', 'auth'])->prefix('integrations')->name('integrations.oauth.')->group(function (): void {
    // Registered before the {plugin} routes so "oauth" is never captured as a plugin key.
    Route::get('/oauth/callback', [ConnectedIntegrationOAuthController::class, 'callback'])
        ->middleware('throttle:20,1')
        ->name('callback');

    Route::get('/{plugin}/connect', [ConnectedIntegrationOAuthController::class, 'connect'])
        ->where('plugin', '[a-z0-9-]+')
        ->name('connect');
    Route::get('/{plugin}/reconnect', [ConnectedIntegrationOAuthController::class, 'reconnect'])
        ->where('plugin', '[a-z0-9-]+')
        ->name('reconnect');
    Route::delete('/{plugin}', [ConnectedIntegrationOAuthController::class, 'disconnect'])
        ->where('plugin', '[a-z0-9-]+')
        ->name('disconnect');
});

if (app()->isLocal()) {
    Route::inertia('/dev/styleguide', 'dev/styleguide')->name('dev.styleguide');
}
