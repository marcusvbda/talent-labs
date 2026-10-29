<?php

namespace App\Client;

use App\Enums\RemoteMode;
use App\Models\JobPosting;
use App\Outreach\Data\PreferenceCriteria;
use App\Outreach\Queries\MatchingJobPostings;
use App\Outreach\Support\WordPattern;
use Illuminate\Database\Eloquent\Builder;

/**
 * How well a pool posting fits the client's preferences, as an integer 0-100. Only the
 * preference fields that are set count; their weights are re-normalised to 100. Ranks
 * and explains the pool, never narrows it.
 */
final class MatchScore
{
    /**
     * Criterion weights, in display order.
     */
    public const array WEIGHTS = ['stack' => 50, 'seniority' => 25, 'location' => 15, 'title' => 10];

    /**
     * The criteria the preferences set, in WEIGHTS order.
     *
     * @return list<'stack'|'seniority'|'location'|'title'>
     */
    public static function criteria(PreferenceCriteria $criteria): array
    {
        $keys = [];

        if ($criteria->stack !== []) {
            $keys[] = 'stack';
        }

        if ($criteria->seniorities !== []) {
            $keys[] = 'seniority';
        }

        if ($criteria->remoteMode === RemoteMode::RemoteOnly
            || ($criteria->remoteMode !== RemoteMode::Any && $criteria->locations !== [])) {
            $keys[] = 'location';
        }

        if ($criteria->titles !== []) {
            $keys[] = 'title';
        }

        return $keys;
    }

    /**
     * Adds `match_score` and `match_location_hit` to a {@see MatchingJobPostings} query
     * (it joins job_posting_profiles).
     *
     * @param  Builder<JobPosting>  $query
     * @return Builder<JobPosting>
     */
    public static function addSelects(Builder $query, PreferenceCriteria $criteria): Builder
    {
        $keys = self::criteria($criteria);

        if ($keys === []) {
            $query->selectRaw('0 as match_score');
        } else {
            $parts = [];
            $bindings = [];

            foreach ($keys as $key) {
                [$sql, $partBindings] = match ($key) {
                    'stack' => self::stackPart($criteria),
                    'seniority' => self::seniorityPart($criteria),
                    'location' => self::locationPart($criteria),
                    'title' => self::titlePart($criteria),
                };

                $parts[] = $sql;
                array_push($bindings, ...$partBindings);
            }

            $bindings[] = array_sum(array_map(fn (string $key): int => self::WEIGHTS[$key], $keys));

            $query->selectRaw('round(('.implode(' + ', $parts).') * 100 / ?::int)::int as match_score', $bindings);
        }

        if ($criteria->locations !== [] && $criteria->remoteMode !== RemoteMode::Any) {
            [$sql, $bindings] = self::locationHit($criteria->locations);
            $query->selectRaw("({$sql}) as match_location_hit", $bindings);
        } else {
            $query->selectRaw('null::boolean as match_location_hit');
        }

        return $query;
    }

    /**
     * The score and its per-criterion breakdown, or null when the posting wasn't loaded
     * through {@see JobPoolQuery} or no criterion is set.
     *
     * @return array{score: int, criteria: list<array<string, mixed>>}|null
     */
    public static function explain(JobPosting $posting, PreferenceCriteria $criteria): ?array
    {
        $score = $posting->getAttribute('match_score');
        $keys = self::criteria($criteria);

        if ($score === null || $keys === []) {
            return null;
        }

        $profile = $posting->profile;
        $entries = [];

        foreach ($keys as $key) {
            $entries[] = match ($key) {
                'stack' => self::explainStack($profile->stack ?? [], $criteria->stack),
                'seniority' => ['key' => 'seniority', 'result' => match (true) {
                    in_array($profile?->seniority, $criteria->seniorities, true) => 'met',
                    in_array($profile?->seniority, [null, 'unknown'], true) => 'not_stated',
                    default => 'missed',
                }],
                'location' => ['key' => 'location', 'result' => self::explainLocation($posting, $criteria->remoteMode)],
                'title' => ['key' => 'title', 'result' => self::titleMatches($criteria->titles, [$posting->title, $profile?->normalized_title]) ? 'met' : 'missed'],
            };
        }

        return ['score' => (int) $score, 'criteria' => $entries];
    }

    /**
     * @param  list<string>  $profileStack
     * @param  list<string>  $wanted
     * @return array{key: string, result: string, matched: list<string>, missing: list<string>}
     */
    private static function explainStack(array $profileStack, array $wanted): array
    {
        $matched = array_values(array_filter($wanted, fn (string $tech): bool => in_array($tech, $profileStack, true)));
        $missing = array_values(array_diff($wanted, $matched));

        return [
            'key' => 'stack',
            'result' => match (true) {
                $missing === [] => 'met',
                $matched !== [] => 'partial',
                default => 'missed',
            },
            'matched' => $matched,
            'missing' => $missing,
        ];
    }

    private static function explainLocation(JobPosting $posting, RemoteMode $mode): string
    {
        $remote = $posting->profile?->is_remote === true;
        $locationHit = (bool) $posting->getAttribute('match_location_hit');

        return match (true) {
            $mode !== RemoteMode::LocationsOnly && $remote => 'remote',
            $mode !== RemoteMode::RemoteOnly && $locationHit => 'location',
            default => 'missed',
        };
    }

    /**
     * Whether any preferred title appears as a whole word (case-insensitive) in one of the texts.
     *
     * @param  list<string>  $titles
     * @param  list<string|null>  $texts
     */
    private static function titleMatches(array $titles, array $texts): bool
    {
        foreach ($titles as $title) {
            $phrase = implode('\\s+', array_map(fn (string $part): string => preg_quote($part, '/'), preg_split('/\s+/u', trim($title)) ?: []));
            $pattern = '/(?<![\p{L}\p{N}])'.$phrase.'(?![\p{L}\p{N}])/iu';

            foreach ($texts as $text) {
                if ($text !== null && preg_match($pattern, $text) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array{0: literal-string, 1: list<mixed>}
     */
    private static function stackPart(PreferenceCriteria $criteria): array
    {
        $placeholders = implode(', ', array_fill(0, count($criteria->stack), '?'));

        return [
            "(50.0 * (select count(*) from unnest(array[{$placeholders}]::text[]) as s(v) where jsonb_exists(job_posting_profiles.stack, s.v)) / ?::int)",
            [...$criteria->stack, count($criteria->stack)],
        ];
    }

    /**
     * @return array{0: literal-string, 1: list<mixed>}
     */
    private static function seniorityPart(PreferenceCriteria $criteria): array
    {
        $placeholders = implode(', ', array_fill(0, count($criteria->seniorities), '?'));

        return [
            "(25.0 * (case when job_posting_profiles.seniority in ({$placeholders}) then 1 when job_posting_profiles.seniority = 'unknown' then 0.5 else 0 end))",
            $criteria->seniorities,
        ];
    }

    /**
     * @return array{0: literal-string, 1: list<mixed>}
     */
    private static function locationPart(PreferenceCriteria $criteria): array
    {
        [$fit, $bindings] = self::locationFit($criteria);

        return ["(15.0 * (case when {$fit} then 1 else 0 end))", $bindings];
    }

    /**
     * @return array{0: literal-string, 1: list<mixed>}
     */
    private static function locationFit(PreferenceCriteria $criteria): array
    {
        if ($criteria->remoteMode === RemoteMode::RemoteOnly) {
            return ['job_posting_profiles.is_remote is true', []];
        }

        [$hit, $bindings] = self::locationHit($criteria->locations);

        return $criteria->remoteMode === RemoteMode::RemoteOrLocations
            ? ["(job_posting_profiles.is_remote is true or ({$hit}))", $bindings]
            : ["({$hit})", $bindings];
    }

    /**
     * Whole-word match of any location against the posting location or the profile's
     * extracted locations (the gate's location rule, copied).
     *
     * @param  list<string>  $locations
     * @return array{0: literal-string, 1: list<mixed>}
     */
    private static function locationHit(array $locations): array
    {
        $clauses = [];
        $bindings = [];

        foreach ($locations as $location) {
            $pattern = WordPattern::toRegex($location);
            $clauses[] = '(job_postings.location ~* ? or exists (select 1 from jsonb_array_elements_text(job_posting_profiles.locations) as l(v) where l.v ~* ?))';
            array_push($bindings, $pattern, $pattern);
        }

        return [implode(' or ', $clauses), $bindings];
    }

    /**
     * @return array{0: literal-string, 1: list<mixed>}
     */
    private static function titlePart(PreferenceCriteria $criteria): array
    {
        [$hit, $bindings] = self::titleHit($criteria->titles);

        return ["(10.0 * (case when ({$hit}) then 1 else 0 end))", $bindings];
    }

    /**
     * @param  list<string>  $titles
     * @return array{0: literal-string, 1: list<mixed>}
     */
    private static function titleHit(array $titles): array
    {
        $clauses = [];
        $bindings = [];

        foreach ($titles as $title) {
            $pattern = WordPattern::toRegex($title);
            $clauses[] = '(job_postings.title ~* ? or job_posting_profiles.normalized_title ~* ?)';
            array_push($bindings, $pattern, $pattern);
        }

        return [implode(' or ', $clauses), $bindings];
    }
}
