<?php

namespace App\Client;

use App\Models\JobPosting;
use App\Models\User;
use App\Outreach\Queries\MatchingJobPostings;
use App\Outreach\Support\StackNormalizer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

final class JobPoolQuery
{
    private const IS_REMOTE = 'coalesce(job_posting_profiles.is_remote, job_postings.is_remote)';

    /**
     * The client's job pool ({@see MatchingJobPostings}) narrowed by the Jobs screen filters,
     * keeping only the newest posting per company, newest first.
     *
     * @param  array{q?: string|null, language?: string|null, seniority?: array<array-key, mixed>|null, remote?: string|null, today?: bool|null, stack?: array<array-key, mixed>|null}  $filters
     * @return Builder<JobPosting>
     */
    public static function forUser(User $user, array $filters = []): Builder
    {
        // "No other pool posting for this company is newer", evaluated against the same filtered pool.
        $candidates = self::filtered($user, $filters)
            ->select(['job_postings.id', 'job_postings.company_id', 'job_postings.first_seen_at']);

        return self::filtered($user, $filters)
            ->whereNotExists(function (QueryBuilder $newer) use ($candidates): void {
                $newer->select(DB::raw(1))
                    ->fromSub($candidates, 'newer')
                    ->whereColumn('newer.company_id', 'job_postings.company_id')
                    ->where(function (QueryBuilder $order): void {
                        $order->whereColumn('newer.first_seen_at', '>', 'job_postings.first_seen_at')
                            ->orWhere(function (QueryBuilder $tie): void {
                                $tie->whereColumn('newer.first_seen_at', '=', 'job_postings.first_seen_at')
                                    ->whereColumn('newer.id', '>', 'job_postings.id');
                            });
                    });
            })
            ->orderByDesc('job_postings.first_seen_at')
            ->orderByDesc('job_postings.id');
    }

    /**
     * Start of the user's current day, expressed in the app timezone the timestamps are stored in.
     */
    public static function todayStartsAt(User $user): CarbonImmutable
    {
        $appTimezone = (string) config('app.timezone');

        return CarbonImmutable::now($user->timezone ?? $appTimezone)
            ->startOfDay()
            ->setTimezone($appTimezone);
    }

    /**
     * @param  array{q?: string|null, language?: string|null, seniority?: array<array-key, mixed>|null, remote?: string|null, today?: bool|null, stack?: array<array-key, mixed>|null}  $filters
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
            $query->where('job_postings.first_seen_at', '>=', self::todayStartsAt($user));
        }

        $stack = StackNormalizer::normalize($filters['stack'] ?? []);

        if ($stack !== []) {
            $placeholders = implode(', ', array_fill(0, count($stack), '?'));
            $query->whereRaw("job_posting_profiles.stack ??| array[{$placeholders}]::text[]", $stack);
        }

        return $query;
    }
}
