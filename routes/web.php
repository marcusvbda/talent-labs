<?php

use App\Http\Controllers\ConnectedIntegrationOAuthController;
use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::put('/locale', [LocaleController::class, 'update'])->name('locale.update');

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
