<?php

namespace App\Support\I18n;

use Illuminate\Http\Request;

final class LocaleResolver
{
    /**
     * Resolve the request locale: user preference, then the `locale` cookie,
     * then Accept-Language (primary subtag), then English.
     */
    public static function resolve(Request $request): string
    {
        /** @var list<string> $supported */
        $supported = config('talent.locales', ['en']);

        $candidates = [];

        if ($request->hasSession()) {
            $candidates[] = $request->user()?->locale;
        }

        $candidates[] = $request->cookie('locale');

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && in_array($candidate, $supported, true)) {
                return $candidate;
            }
        }

        foreach ($request->getLanguages() as $language) {
            $primary = strtolower(strtok($language, '-_') ?: '');

            if (in_array($primary, $supported, true)) {
                return $primary;
            }
        }

        return 'en';
    }
}
