<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('talent.brand.name') }}</title>
        </x-inertia::head>
        @if ($page['component'] === 'landing')
            @php
                $landingBrand = (string) config('talent.brand.name');
                $landingStrings = \App\Support\I18n\Translations::for(app()->getLocale());
                $landingEssentials = [
                    'brand' => $landingBrand,
                    'description' => str_replace(':brand', $landingBrand, $landingStrings['landing.meta.description'] ?? ''),
                    'emailNote' => str_replace(':brand', $landingBrand, $landingStrings['landing.email.note'] ?? ''),
                ];
            @endphp
            {{-- Keyed like Inertia-managed head tags so the client <Head> replaces it: one description after hydration. --}}
            <meta name="description" data-inertia="description" content="{{ $landingEssentials['description'] }}">
        @endif
    </head>
    <body class="font-sans antialiased">
        @if ($page['component'] === 'landing')
            @include('partials.landing-essentials', $landingEssentials)
        @endif
        <x-inertia::app />
    </body>
</html>
