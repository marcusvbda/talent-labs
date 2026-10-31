<?php

namespace App\Contacts\Support;

use Illuminate\Support\Str;

class NamedEmailCandidates
{
    /**
     * Common address patterns for a person at a domain, in order: first@, first.last@, flast@,
     * firstlast@. Uses the first and last words of the name; empty for single-word names.
     *
     * @return list<string>
     */
    public static function for(string $name, string $domain): array
    {
        $domain = strtolower(trim($domain));

        $words = array_values(array_filter(
            array_map(
                fn (string $word): string => (string) preg_replace('/[^a-z]/', '', strtolower(Str::ascii($word))),
                preg_split('/\s+/', trim($name)) ?: [],
            ),
            fn (string $word): bool => $word !== '',
        ));

        if ($domain === '' || count($words) < 2) {
            return [];
        }

        $first = $words[0];
        $last = $words[count($words) - 1];

        return array_values(array_unique([
            "{$first}@{$domain}",
            "{$first}.{$last}@{$domain}",
            $first[0]."{$last}@{$domain}",
            "{$first}{$last}@{$domain}",
        ]));
    }
}
