<?php

namespace App\Client;

use App\Collection\Support\PostingDay;
use App\Models\JobPosting;
use App\Models\User;
use App\Outreach\Data\PreferenceCriteria;
use App\Outreach\Queries\MatchingJobPostings;
use App\Outreach\Support\StackNormalizer;
use Illuminate\Database\Eloquent\Builder;

final class JobPoolQuery
{
    private const IS_REMOTE = 'coalesce(job_posting_profiles.is_remote, job_postings.is_remote)';

    /**
     * The client's job pool ({@see MatchingJobPostings}) narrowed by the Jobs screen filters,
     * every matching posting once (several postings of one company all show), each with its
     * {@see MatchScore} (`match_score`, `match_location_hit`). Newest first by default; best match
     * first with `sort=match` (ties: better recipient tier, then newest first). The pool is a derived table aliased `job_postings`.
     *
     * @param  array{q?: string|null, language?: string|null, seniority?: array<array-key, mixed>|null, remote?: string|null, today?: bool|null, stack?: array<array-key, mixed>|null, sort?: string|null}  $filters
     * @return Builder<JobPosting>
     */
    public static function forUser(User $user, array $filters = []): Builder
    {
        $criteria = $user->jobPreference !== null
            ? PreferenceCriteria::fromPreference($user->jobPreference)
            : PreferenceCriteria::empty();

        $byMatch = ($filters['sort'] ?? null) === 'match';
        $filtered = self::filtered($user, $filters);

        if ($byMatch) {
            MatchingJobPostings::withRecipientTierRank($filtered);
        }

        $pool = MatchScore::addSelects($filtered, $criteria);
        $query = JobPosting::query()->fromSub($pool, 'job_postings');

        if ($byMatch) {
            // Equal scores: better recipient tier first (internal only, never exposed).
            $query->orderByDesc('job_postings.match_score')
                ->orderBy('job_postings.recipient_tier_rank');
        }

        return $query
            ->orderByDesc('job_postings.first_seen_at')
            ->orderByDesc('job_postings.id');
    }

    /**
     * @param  array{q?: string|null, language?: string|null, seniority?: array<array-key, mixed>|null, remote?: string|null, today?: bool|null, stack?: array<array-key, mixed>|null, sort?: string|null}  $filters
     * @return Builder<JobPosting>
     */
    private static function filtered(User $user, array $filters): Builder
    {
        $query = MatchingJobPostings::forUser($user);

        $q = trim((string) ($filters['q'] ?? ''));

        if ($q !== '') {
            $pattern = '%'.addcslashes($q, '\\%_').'%';

            $query->where(fn (Builder $group) => $group
                ->orWhere('job_postings.title', 'ilike', $pattern)
                ->orWhere('companies.name', 'ilike', $pattern));
        }

        $language = $filters['language'] ?? null;

        if (in_array($language, ['en', 'pt'], true)) {
            $query->where('job_posting_profiles.language', $language);
        }

        $seniority = array_values(array_filter($filters['seniority'] ?? [], 'is_string'));

        if ($seniority !== []) {
            $query->whereIn('job_posting_profiles.seniority', $seniority);
        }

        match ($filters['remote'] ?? null) {
            'remote' => $query->whereRaw(self::IS_REMOTE.' = true'),
            'not_remote' => $query->where(fn (Builder $group) => $group
                ->whereRaw(self::IS_REMOTE.' is null')
                ->orWhereRaw(self::IS_REMOTE.' = false')),
            default => null,
        };

        if (($filters['today'] ?? false) === true) {
            $query->where('first_run.started_at', '>=', PostingDay::todayStartsAt());
        }

        $stack = StackNormalizer::normalize($filters['stack'] ?? []);

        if ($stack !== []) {
            $placeholders = implode(', ', array_fill(0, count($stack), '?'));
            $query->whereRaw("job_posting_profiles.stack ??| array[{$placeholders}]::text[]", $stack);
        }

        return $query;
    }
}
