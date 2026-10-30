<?php

use App\Http\Controllers\CspReportController;
use App\Http\Middleware\AddContentSecurityPolicy;
use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\EnsureActiveClient;
use App\Http\Middleware\EnsurePlanCanChooseJobs;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use App\Support\I18n\LocaleResolver;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Support\Facades\Route;
use Inertia\ExceptionResponse;
use Inertia\Inertia;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function (): void {
            require __DIR__.'/../routes/cashier.php';

            // Browser CSP reports: outside the web group (no session, no CSRF).
            Route::post('/csp-report', CspReportController::class)
                ->middleware('throttle:30,1,csp-report')
                ->name('csp.report');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            // Report-only CSP for the client app; the Filament panel does not use the web group.
            AddContentSecurityPolicy::class,
        ]);

        // Readable unencrypted so error pages rendered outside the web group can use it.
        $middleware->encryptCookies(except: ['locale']);

        // Stripe posts signed webhooks to Cashier's route; no CSRF token.
        $middleware->validateCsrfTokens(except: ['stripe/*']);

        $middleware->alias([
            'client' => EnsureActiveClient::class,
            'plan.jobs' => EnsurePlanCanChooseJobs::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));

        // Rejects forged Host headers outside local/tests: APP_URL host and its subdomains.
        $middleware->trustHosts();

        // Global so the Filament panel (own stack) and error responses get them too.
        $middleware->append(AddSecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Expired or tampered verification links go back to the notice page instead of a bare 403.
        $exceptions->render(function (InvalidSignatureException $e, Request $request) {
            if (! $request->routeIs('verification.verify')) {
                return null;
            }

            return redirect()->route('verification.notice')
                ->with('error', __('auth.verify.link_invalid'));
        });

        // Branded Inertia error pages for the client app; Filament keeps its own.
        Inertia::handleExceptionsUsing(function (ExceptionResponse $response) {
            $request = $response->request;
            $status = $response->statusCode();
            $adminPath = trim((string) config('filament.admin_panel_path'), '/');

            if (config('app.debug')
                || ! in_array($status, [403, 404, 419, 500, 503], true)
                || $request->expectsJson()
                || $request->hasHeader('X-Livewire')
                || ($adminPath !== '' && $request->is($adminPath, "{$adminPath}/*"))) {
                return null;
            }

            // The web group (SetLocale) may not have run, e.g. on unmatched routes.
            app()->setLocale(LocaleResolver::resolve($request));

            return $response->render('errors/error', ['status' => $status])->withSharedData();
        });
    })->create();
