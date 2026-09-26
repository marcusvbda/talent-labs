<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\I18n\Translations;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();
        $locale = app()->getLocale();

        return [
            ...parent::share($request),
            'app' => [
                'brand' => [
                    'name' => config('talent.brand.name'),
                    'wordmark' => config('talent.brand.wordmark'),
                ],
                'env' => config('app.env'),
                'useFixtures' => config('talent.client.use_fixtures'),
            ],
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'initials' => $user->initials(),
                    'locale' => $user->locale,
                ] : null,
            ],
            'locale' => $locale,
            'locales' => config('talent.locales'),
            'translations' => Translations::for($locale),
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ];
    }
}
