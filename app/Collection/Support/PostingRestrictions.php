<?php

namespace App\Collection\Support;

/**
 * Deterministic (no AI) detection of location / work-authorization / workplace / timezone
 * restrictions in a posting. Stored on job_postings.restrictions at collection time.
 */
final class PostingRestrictions
{
    /**
     * Words that name each canonical region (matched as whole words in preference locations).
     *
     * @var array<string, list<string>>
     */
    public const array REGION_ALIASES = [
        'us' => ['us', 'usa', 'u.s.', 'united states', 'america'],
        'canada' => ['canada'],
        'uk' => ['uk', 'united kingdom', 'england', 'london'],
        'europe' => ['eu', 'europe', 'european union', 'emea'],
        'brazil' => ['brazil', 'brasil'],
        'emea' => ['emea', 'europe', 'eu'],
        'latam' => ['latam', 'latin america', 'brazil', 'brasil'],
        'americas' => ['americas', 'us', 'usa', 'canada', 'latam', 'brazil', 'brasil'],
    ];

    private const string REGION_TOKENS = 'us|u\.s\.|usa|united states|canada|uk|united kingdom|eu|europe|brazil|brasil|emea|latam|americas';

    private const string AUTHORIZATION_PATTERN = '/(authori[sz]ed to work|work authori[sz]ation|right to work|must (be located|reside|live) in|unable to sponsor|no (visa )?sponsorship|not (able|offering) to sponsor)/u';

    private const string ONSITE_PATTERN = '/\b(on-?site|in[- ]office|in the office)\b/u';

    private const string NOT_ONSITE_PATTERN = '/\bnot (on-?site|in[- ]office)\b/u';

    private const string HYBRID_PATTERN = '/\bhybrid\b/u';

    private const string TIMEZONE_PATTERN = '/\b(est|edt|pst|pdt|cst|cet|cest|gmt[+-]?\d*|utc[+-]?\d*|brt)\b.{0,20}(hours|overlap|time ?zone)/u';

    /**
     * @return list<array{kind: string, value: string|null}> unique by kind + value
     */
    public static function detect(?string $title, ?string $location, ?string $text): array
    {
        $haystack = mb_strtolower(implode("\n", [(string) $title, (string) $location, mb_substr((string) $text, 0, 6000)]));
        $regions = self::REGION_TOKENS;
        $found = [];

        $regionPatterns = [
            '/\b(us|u\.s\.|usa|united states|canada|uk|united kingdom|eu|europe|brazil|brasil)[- ]?(only|based)\b/u',
            // (?![a-z]) instead of \b so "remote (u.s.)" still matches after the trailing dot.
            "/remote\\s*[\\(\\-–,]\\s*({$regions})(?![a-z])/u",
            '/\b(us|u\.s\.|usa)\s+remote\b/u',
        ];

        foreach ($regionPatterns as $pattern) {
            if (preg_match_all($pattern, $haystack, $matches) > 0) {
                foreach ($matches[1] as $token) {
                    $found[] = ['kind' => 'region', 'value' => self::canonicalRegion($token)];
                }
            }
        }

        if (preg_match_all(self::AUTHORIZATION_PATTERN, $haystack, $matches, PREG_OFFSET_CAPTURE) > 0) {
            foreach ($matches[0] as [$phrase, $offset]) {
                // Byte offsets: the window may cut a multibyte char, so it is matched without /u.
                $window = substr($haystack, max(0, $offset - 40), strlen($phrase) + 80);
                $value = preg_match("/(?<![a-z])({$regions})(?![a-z])/", $window, $region) === 1 ? self::canonicalRegion($region[1]) : null;
                $found[] = ['kind' => 'work_authorization', 'value' => $value];
            }
        }

        if (preg_match(self::ONSITE_PATTERN, $haystack) === 1 && preg_match(self::NOT_ONSITE_PATTERN, $haystack) !== 1) {
            $found[] = ['kind' => 'onsite', 'value' => null];
        }

        if (preg_match(self::HYBRID_PATTERN, $haystack) === 1) {
            $found[] = ['kind' => 'hybrid', 'value' => null];
        }

        if (preg_match_all(self::TIMEZONE_PATTERN, $haystack, $matches) > 0) {
            foreach ($matches[1] as $zone) {
                $found[] = ['kind' => 'timezone', 'value' => $zone];
            }
        }

        $unique = [];

        foreach ($found as $restriction) {
            $unique[$restriction['kind'].':'.$restriction['value']] = $restriction;
        }

        return array_values($unique);
    }

    private static function canonicalRegion(string $token): string
    {
        return match ($token) {
            'us', 'u.s.', 'usa', 'united states' => 'us',
            'uk', 'united kingdom' => 'uk',
            'eu', 'europe' => 'europe',
            'brazil', 'brasil' => 'brazil',
            default => $token,
        };
    }
}
