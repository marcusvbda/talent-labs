<?php

namespace App\Support\I18n;

use Illuminate\Support\Facades\Cache;

final class Translations
{
    /**
     * Flat key => string map for the given locale, with English as fallback.
     *
     * @return array<string, string>
     */
    public static function for(string $locale): array
    {
        /** @var list<string> $locales */
        $locales = config('talent.locales', ['en']);

        if (! in_array($locale, $locales, true)) {
            $locale = 'en';
        }

        $fallbackPath = lang_path('en.json');
        $localePath = lang_path("{$locale}.json");

        $version = implode('.', [
            self::mtime($fallbackPath),
            self::mtime($localePath),
        ]);

        return Cache::rememberForever(
            "translations.{$locale}.{$version}",
            fn (): array => [
                ...self::read($fallbackPath),
                ...self::read($localePath),
            ],
        );
    }

    private static function mtime(string $path): int
    {
        return is_file($path) ? (int) filemtime($path) : 0;
    }

    /**
     * @return array<string, string>
     */
    private static function read(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            return [];
        }

        /** @var array<string, string> $decoded */
        return $decoded;
    }
}
