<?php

namespace App\Outreach\Support;

final class WordPattern
{
    public static function toRegex(string $term): string
    {
        $term = trim((string) preg_replace('/\s+/u', ' ', $term));

        $escaped = (string) preg_replace('/[\\\\.^$|?*+()\[\]{}]/', '\\\\$0', $term);

        // \m and \M are Postgres ARE word boundaries (for use with ~*), not PCRE.
        $prefix = preg_match('/^[\p{L}\p{N}]/u', $term) === 1 ? '\m' : '';
        $suffix = preg_match('/[\p{L}\p{N}]$/u', $term) === 1 ? '\M' : '';

        return $prefix.$escaped.$suffix;
    }
}
