<?php

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
use Inertia\ExceptionResponse;
use Inertia\Inertia;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Readable unencrypted so error pages rendered outside the web group can use it.
        $middleware->encryptCookies(except: ['locale']);

        $middleware->alias([
            'client' => EnsureActiveClient::class,
            'plan.jobs' => EnsurePlanCanChooseJobs::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Branded Inertia error pages for the client app; Filament keeps its own.
        Inertia::handleExceptionsUsing(function (ExceptionResponse $response) {
            $request = $response->request;
            $status = $response->statusCode();

            if (config('app.debug')
                || ! in_array($status, [403, 404, 419, 500, 503], true)
                || $request->expectsJson()
                || $request->hasHeader('X-Livewire')
                || $request->is('admin', 'admin/*')) {
                return null;
            }

            // The web group (SetLocale) may not have run, e.g. on unmatched routes.
            app()->setLocale(LocaleResolver::resolve($request));

            return $response->render('errors/error', ['status' => $status])->withSharedData();
        });
    })->create();
