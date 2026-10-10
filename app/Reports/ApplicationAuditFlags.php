<?php

namespace App\Reports;

use App\Enums\RemoteMode;
use App\Outreach\OutreachLimits;

/**
 * Pure, deterministic heuristics behind reports:application-audit (no DB access).
 * Every method returns null when the flag is not raised, otherwise a short reason.
 */
final class ApplicationAuditFlags
{
    private const string ROLE_PATTERN = '/(engineer|developer|programmer|software|swe|sdet|devops|sre|frontend|front-end|backend|back-end|full[- ]?stack|manager|designer|support|success|product|architect|scientist|analyst|lead|head of|director|researcher|qa|ios|android)/i';

    private const string TITLE_SEPARATORS = '/ · | \| | \/ | & | and |;/i';

    private const string BULLET_PATTERN = '/^\s*(?:[-*•]|\d+[.)])\s+(.+)$/u';

    private const string EXCLUDED_TITLE_PATTERN = '/\b(manager|director|head of|vp|vice president|chief|cto|ceo|intern|internship|trainee)\b/i';

    private const string DEV_TITLE_PATTERN = '/(engineer|developer|programmer|software|backend|frontend|full ?stack|devops|sre|mobile|ios|android)/i';

    /**
     * Role families a dev profile is expected to apply to.
     *
     * @var list<string>
     */
    private const array DEV_FAMILIES = ['backend', 'frontend', 'fullstack', 'software', 'mobile', 'devops'];

    private const string REGION_TOKENS = 'us|u\.s\.|usa|united states|canada|uk|united kingdom|eu|europe|brazil|brasil|emea|latam|americas';

    /**
     * Same aliases Phase 5 PostingRestrictions uses: a preference location names a region when
     * one of these words appears in it as a whole word.
     *
     * @var array<string, list<string>>
     */
    private const array REGION_ALIASES = [
        'us' => ['us', 'usa', 'u.s.', 'united states', 'america'],
        'canada' => ['canada'],
        'uk' => ['uk', 'united kingdom', 'england', 'london'],
        'europe' => ['eu', 'europe', 'european union', 'emea'],
        'brazil' => ['brazil', 'brasil'],
        'emea' => ['emea', 'europe', 'eu'],
        'latam' => ['latam', 'latin america', 'brazil', 'brasil'],
        'americas' => ['americas', 'us', 'usa', 'canada', 'latam', 'brazil', 'brasil'],
    ];

    /**
     * Flag a: one application covering two or more roles.
     *
     * @param  array<string, mixed>  $raw
     */
    public static function multiRole(?string $postingTitle, string $subject, bool $isHackerNews, array $raw, ?string $descriptionText): ?string
    {
        foreach (['title' => $postingTitle, 'subject' => $subject] as $field => $value) {
            if ($value !== null && self::splitsIntoRoles($value)) {
                return "{$field} lists several roles";
            }
        }

        if (! $isHackerNews) {
            return null;
        }

        $html = is_string($raw['text'] ?? null) ? $raw['text'] : '';

        if ($html !== '' && self::headerRoleCount($html) >= 2) {
            return 'HN header lists several roles';
        }

        if (self::bodyRoleLineCount((string) $descriptionText) >= 2) {
            return 'body lists several roles';
        }

        return null;
    }

    /**
     * Flag c: restrictions found in the posting that the user's preferences can't satisfy.
     * Null $remoteMode means the user has no preference row: any restriction counts.
     *
     * @param  list<string>  $locations
     * @return list<string> the incompatible restriction kinds (empty = not flagged)
     */
    public static function geo(?string $title, ?string $location, ?string $text, ?RemoteMode $remoteMode, array $locations): array
    {
        $restrictions = self::restrictions($title, $location, $text);

        if ($remoteMode === null) {
            return array_values(array_unique(array_map(
                fn (array $restriction): string => self::describe($restriction),
                $restrictions,
            )));
        }

        $named = self::regionsNamedBy($locations);
        $reasons = [];

        foreach ($restrictions as $restriction) {
            $incompatible = match ($restriction['kind']) {
                'hybrid', 'onsite' => $remoteMode === RemoteMode::RemoteOnly,
                'region' => ! in_array($restriction['value'], $named, true),
                default => true,
            };

            if ($incompatible) {
                $reasons[] = self::describe($restriction);
            }
        }

        return array_values(array_unique($reasons));
    }

    /**
     * Flag d: out-of-profile role or seniority.
     *
     * @param  list<string>  $preferenceTitles
     * @param  list<string>  $preferenceSeniorities
     * @return list<string> reasons (empty = not flagged)
     */
    public static function profile(?string $postingTitle, ?string $seniority, ?string $roleFamily, array $preferenceTitles, array $preferenceSeniorities): array
    {
        $reasons = [];

        if ($postingTitle !== null && preg_match(self::EXCLUDED_TITLE_PATTERN, $postingTitle, $match) === 1) {
            $reasons[] = 'title: '.strtolower($match[1]);
        }

        if ($seniority !== null && $seniority !== 'unknown' && $preferenceSeniorities !== [] && ! in_array($seniority, $preferenceSeniorities, true)) {
            $reasons[] = "seniority: {$seniority}";
        }

        $isDevProfile = array_any($preferenceTitles, fn (string $title): bool => preg_match(self::DEV_TITLE_PATTERN, $title) === 1);

        if ($isDevProfile && ! in_array($roleFamily, self::DEV_FAMILIES, true)) {
            $reasons[] = 'role family: '.($roleFamily ?? 'none');
        }

        return $reasons;
    }

    /**
     * Flag e: the lowercased local part when it is a generic alias, else null.
     */
    public static function genericRecipient(string $localPart): ?string
    {
        return in_array($localPart, OutreachLimits::GENERIC_LOCAL_PARTS, true) ? $localPart : null;
    }

    private static function splitsIntoRoles(string $value): bool
    {
        $parts = array_values(array_filter(
            array_map(trim(...), preg_split(self::TITLE_SEPARATORS, $value) ?: []),
            fn (string $part): bool => $part !== '',
        ));

        return count($parts) >= 2
            && array_all($parts, fn (string $part): bool => preg_match(self::ROLE_PATTERN, $part) === 1);
    }

    /**
     * Role-like header segments of an HN comment (segment 0, the company, is ignored).
     */
    private static function headerRoleCount(string $html): int
    {
        $header = preg_split('/<p>/i', $html, 2)[0] ?? $html;
        $header = html_entity_decode(strip_tags($header), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $segments = array_slice(array_map(trim(...), explode('|', $header)), 1);

        return count(array_filter($segments, fn (string $segment): bool => $segment !== '' && preg_match(self::ROLE_PATTERN, $segment) === 1));
    }

    /**
     * Bulleted lines ("- ", "* ", "• ", "1. ", "1) ") of at most 120 chars that read as a role.
     */
    private static function bodyRoleLineCount(string $text): int
    {
        $count = 0;

        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            if (preg_match(self::BULLET_PATTERN, $line, $match) !== 1) {
                continue;
            }

            $phrase = trim($match[1]);

            if (mb_strlen($phrase) <= 120 && preg_match(self::ROLE_PATTERN, $phrase) === 1) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Location / work-authorization / workplace / timezone restrictions in the posting.
     *
     * @return list<array{kind: string, value: string|null}>
     */
    private static function restrictions(?string $title, ?string $location, ?string $text): array
    {
        $haystack = mb_strtolower(implode("\n", [(string) $title, (string) $location, mb_substr((string) $text, 0, 6000)]));
        $regions = self::REGION_TOKENS;
        $found = [];

        $regionPatterns = [
            '/\b(us|u\.s\.|usa|united states|canada|uk|united kingdom|eu|europe|brazil|brasil)[- ]?(only|based)\b/u',
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

        $authorization = '/(authori[sz]ed to work|work authori[sz]ation|right to work|must (be located|reside|live) in|unable to sponsor|no (visa )?sponsorship|not (able|offering) to sponsor)/u';

        if (preg_match_all($authorization, $haystack, $matches, PREG_OFFSET_CAPTURE) > 0) {
            foreach ($matches[0] as [$phrase, $offset]) {
                // Byte offsets: the window may cut a multibyte char, so it is matched without /u.
                $window = substr($haystack, max(0, $offset - 40), strlen($phrase) + 80);
                $value = preg_match("/(?<![a-z])({$regions})(?![a-z])/", $window, $region) === 1 ? self::canonicalRegion($region[1]) : null;
                $found[] = ['kind' => 'work_authorization', 'value' => $value];
            }
        }

        if (preg_match('/\b(on-?site|in[- ]office|in the office)\b/u', $haystack) === 1 && preg_match('/\bnot (on-?site|in[- ]office)\b/u', $haystack) !== 1) {
            $found[] = ['kind' => 'onsite', 'value' => null];
        }

        if (preg_match('/\bhybrid\b/u', $haystack) === 1) {
            $found[] = ['kind' => 'hybrid', 'value' => null];
        }

        if (preg_match_all('/\b(est|edt|pst|pdt|cst|cet|cest|gmt[+-]?\d*|utc[+-]?\d*|brt)\b.{0,20}(hours|overlap|time ?zone)/u', $haystack, $matches) > 0) {
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

    /**
     * Canonical regions named (whole word, case-insensitive) by any preference location.
     *
     * @param  list<string>  $locations
     * @return list<string>
     */
    private static function regionsNamedBy(array $locations): array
    {
        $named = [];

        foreach (self::REGION_ALIASES as $region => $aliases) {
            foreach ($aliases as $alias) {
                $pattern = '/(?<![\p{L}\p{N}])'.preg_quote($alias, '/').'(?![\p{L}\p{N}])/iu';

                if (array_any($locations, fn (string $location): bool => preg_match($pattern, $location) === 1)) {
                    $named[] = $region;

                    continue 2;
                }
            }
        }

        return $named;
    }

    /**
     * @param  array{kind: string, value: string|null}  $restriction
     */
    private static function describe(array $restriction): string
    {
        return $restriction['value'] === null ? $restriction['kind'] : "{$restriction['kind']}: {$restriction['value']}";
    }
}
