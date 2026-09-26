<?php

namespace App\Outreach\Queries;

use App\Enums\ContactConfidence;
use App\Enums\OutreachStatus;
use App\Enums\ProfileStatus;
use App\Models\JobPosting;
use App\Models\User;
use App\Outreach\OutreachLimits;
use App\Outreach\Support\StackNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class MatchingJobPostings
{
    /**
     * The single source of truth for which postings a client sees and may apply to.
     *
     * The base pool is every posting this client can still send an application for: its
     * company is verified, its AI profile is done, the company has a recipient we can
     * write to (an smtp_verified contact in the priority list) and this client has not
     * applied to that company yet. Only postings whose role family is one of the target
     * families (talent.collection.target_role_families) are in the pool, so a company verified
     * through a dev posting never surfaces its non-target (e.g. sales) postings. Each filled preference narrows the pool; with no
     * filters at all the whole pool is returned, unfiltered.
     *
     * @return Builder<JobPosting>
     */
    public static function forUser(User $user): Builder
    {
        $query = JobPosting::query()
            ->select('job_postings.*')
            ->join('job_posting_profiles', 'job_posting_profiles.job_posting_id', '=', 'job_postings.id')
            ->join('companies', 'companies.id', '=', 'job_postings.company_id')
            ->where('companies.outreach_status', OutreachStatus::Verified->value)
            ->where('job_posting_profiles.status', ProfileStatus::Done->value)
            ->whereIn('job_postings.role_family', config('talent.collection.target_role_families'))
            ->whereExists(function ($contacts): void {
                $contacts->select(DB::raw(1))
                    ->from('contacts')
                    ->whereColumn('contacts.company_id', 'job_postings.company_id')
                    ->where('contacts.confidence', ContactConfidence::SmtpVerified->value)
                    ->whereIn('contacts.local_part', OutreachLimits::RECIPIENT_PRIORITY);
            })
            ->whereNotExists(function ($applications) use ($user): void {
                $applications->select(DB::raw(1))
                    ->from('applications')
                    ->whereColumn('applications.company_id', 'job_postings.company_id')
                    ->where('applications.user_id', $user->id);
            });

        $preference = $user->jobPreference;

        if ($preference === null) {
            return $query;
        }

        $titles = self::clean($preference->titles);
        $keywords = self::clean($preference->keywords);
        $stack = StackNormalizer::normalize($preference->stack ?? []);
        $locations = self::clean($preference->locations);

        if ($titles !== []) {
            $query->where(function (Builder $group) use ($titles): void {
                foreach ($titles as $title) {
                    $pattern = self::likePattern($title);
                    $group
                        ->orWhere('job_postings.title', 'ilike', $pattern)
                        ->orWhere('job_posting_profiles.normalized_title', 'ilike', $pattern);
                }
            });
        }

        if ($keywords !== []) {
            $query->where(function (Builder $group) use ($keywords): void {
                foreach ($keywords as $keyword) {
                    $group->orWhere('job_postings.description_text', 'ilike', self::likePattern($keyword));
                }
            });
        }

        if ($stack !== []) {
            $placeholders = implode(', ', array_fill(0, count($stack), '?'));
            $query->whereRaw("job_posting_profiles.stack ??| array[{$placeholders}]::text[]", $stack);
        }

        // No locations means no location filter; "accept remote" only widens a filled list.
        if ($locations !== []) {
            $acceptsRemote = $preference->accepts_remote;

            $query->where(function (Builder $group) use ($locations, $acceptsRemote): void {
                if ($acceptsRemote) {
                    $group->orWhere('job_posting_profiles.is_remote', true);
                }

                foreach ($locations as $location) {
                    $pattern = self::likePattern($location);
                    $group
                        ->orWhere('job_postings.location', 'ilike', $pattern)
                        ->orWhereRaw(
                            'exists (select 1 from jsonb_array_elements_text(job_posting_profiles.locations) as l(v) where l.v ilike ?)',
                            [$pattern],
                        );
                }
            });
        }

        return $query;
    }

    /**
     * @param  array<array-key, mixed>|null  $values
     * @return list<string>
     */
    private static function clean(?array $values): array
    {
        $cleaned = [];

        foreach ($values ?? [] as $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $value = trim((string) $value);

            if ($value !== '') {
                $cleaned[] = $value;
            }
        }

        return array_values(array_unique($cleaned));
    }

    private static function likePattern(string $value): string
    {
        return '%'.addcslashes($value, '\\%_').'%';
    }
}
