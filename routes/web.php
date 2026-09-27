<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Client\ApplicationsPageController;
use App\Http\Controllers\Client\DashboardPageController;
use App\Http\Controllers\Client\Internal\AccountController;
use App\Http\Controllers\Client\Internal\AccountExportController;
use App\Http\Controllers\Client\Internal\AccountStatusController;
use App\Http\Controllers\Client\Internal\ApplicationsController;
use App\Http\Controllers\Client\Internal\ChartController;
use App\Http\Controllers\Client\Internal\DashboardController;
use App\Http\Controllers\Client\Internal\JobsController;
use App\Http\Controllers\Client\Internal\NotificationsController;
use App\Http\Controllers\Client\Internal\OnboardingBasicsController;
use App\Http\Controllers\Client\Internal\PreferencesController;
use App\Http\Controllers\Client\JobsPageController;
use App\Http\Controllers\ConnectedIntegrationOAuthController;
use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'landing')->name('home');

Route::get('/dashboard', DashboardPageController::class)->middleware(['auth', 'client'])->name('dashboard');
Route::inertia('/onboarding', 'onboarding')->middleware(['auth', 'client'])->name('onboarding');

Route::get('/jobs', JobsPageController::class)->middleware(['auth', 'client'])->name('jobs');
Route::get('/applications', ApplicationsPageController::class)->middleware(['auth', 'client'])->name('applications');

Route::inertia('/profiles', 'profiles')->middleware(['auth', 'client'])->name('profiles');

Route::inertia('/preferences', 'preferences')->middleware(['auth', 'client'])->name('preferences');

Route::inertia('/plans', 'plans')->middleware(['auth', 'client'])->name('plans');

Route::inertia('/account', 'account')->middleware(['auth', 'client'])->name('account');

Route::middleware(['auth', 'client', 'throttle:120,1'])
    ->prefix('internal')->name('internal.')
    ->group(function (): void {
        Route::get('/account/status', AccountStatusController::class)->name('account.status');
        Route::put('/onboarding/basics', OnboardingBasicsController::class)->name('onboarding.basics');
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::get('/dashboard/chart', ChartController::class)->name('dashboard.chart');
        Route::get('/jobs', [JobsController::class, 'index'])->name('jobs.index');
        Route::get('/jobs/{id}', [JobsController::class, 'show'])->whereNumber('id')->name('jobs.show');
        Route::get('/applications', [ApplicationsController::class, 'index'])->name('applications.index');
        Route::get('/applications/counts', [ApplicationsController::class, 'counts'])->name('applications.counts');
        Route::get('/applications/{application}', [ApplicationsController::class, 'show'])->name('applications.show');
        Route::get('/notifications', [NotificationsController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/read-all', [NotificationsController::class, 'readAll'])->name('notifications.read-all');
        Route::get('/account', [AccountController::class, 'show'])->name('account.show');
        Route::put('/account', [AccountController::class, 'update'])->name('account.update');
        Route::put('/account/password', [AccountController::class, 'updatePassword'])->name('account.password');
        Route::delete('/account', [AccountController::class, 'destroy'])->name('account.destroy');
        Route::get('/account/export', AccountExportController::class)->name('account.export');
        Route::get('/preferences', [PreferencesController::class, 'show'])->name('preferences.show');
        Route::put('/preferences', [PreferencesController::class, 'update'])->name('preferences.update');
        Route::post('/preferences/preview', [PreferencesController::class, 'preview'])
            ->middleware('throttle:60,1,preferences-preview')
            ->name('preferences.preview');
    });

Route::put('/locale', [LocaleController::class, 'update'])->name('locale.update');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('login.store');

    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('register.store');
    Route::inertia('/register/closed', 'auth/closed')->name('register.closed');
    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('password.email');
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('password.update');
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
