<?php

namespace App\Outreach\Queries;

use App\Collection\Support\PostingDay;
use App\Collection\Support\PostingRestrictions;
use App\Enums\ContactConfidence;
use App\Enums\OutreachStatus;
use App\Enums\ProfileStatus;
use App\Enums\RemoteMode;
use App\Models\ApplicationProfile;
use App\Models\JobPosting;
use App\Models\User;
use App\Outreach\Data\PreferenceCriteria;
use App\Outreach\OutreachLimits;
use App\Outreach\Support\StackNormalizer;
use App\Outreach\Support\WordPattern;
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
     * through a dev posting never surfaces its non-target (e.g. sales) postings. Postings marked
     * not eligible (ineligible_reason, e.g. multi-role posts) never enter the pool. Postings are
     * left out unless their language has an active, complete application profile for this
     * user (skippable with $withLanguageRule = false). Only postings whose first run (collection_run_id)
     * started inside the last talent.collection.window_days app-timezone days, today included, are kept.
     *
     * Preferences narrow the pool: terms inside one field are ORed, fields are ANDed, and an
     * empty field applies no filter (no preferences at all = the whole pool). Titles,
     * locations and exclude words match whole words, case-insensitively (WordPattern + ~*).
     * Seniorities match the profile seniority; "unknown" also passes when
     * talent.matching.unknown_seniority_passes is on. Remote modes: remote_only keeps remote
     * postings and ignores locations; remote_or_locations keeps remote postings or postings
     * in a listed location (no filter when locations are empty); locations_only keeps
     * postings in a listed location. Each exclude word drops postings whose title,
     * normalized title or stack contains it. The description is never read.
     *
     * Detected restrictions (job_postings.restrictions, PostingRestrictions) are enforced
     * last, with or without preferences. A location "names" a region when it contains one of
     * the region's PostingRestrictions::REGION_ALIASES as a whole word (case-insensitive).
     * A region / work_authorization restriction with a value passes only when a preference
     * location names that region (no locations = excluded); a work_authorization restriction
     * without a value always excludes. An onsite / hybrid posting is treated as not remote:
     * excluded under remote_only, otherwise kept only when it matches a preference location
     * (no locations = excluded). Timezone restrictions are recorded only, never filtered.
     *
     * @return Builder<JobPosting>
     */
    public static function forUser(User $user, ?PreferenceCriteria $override = null, bool $withLanguageRule = true): Builder
    {
        $query = JobPosting::query()
            ->select('job_postings.*')
            ->join('job_posting_profiles', 'job_posting_profiles.job_posting_id', '=', 'job_postings.id')
            ->join('companies', 'companies.id', '=', 'job_postings.company_id')
            ->join('collection_runs as first_run', 'first_run.id', '=', 'job_postings.collection_run_id')
            ->where('companies.outreach_status', OutreachStatus::Verified->value)
            ->where('job_posting_profiles.status', ProfileStatus::Done->value)
            ->when($withLanguageRule, fn (Builder $query) => $query->whereIn('job_posting_profiles.language', ApplicationProfile::activeCompleteLanguagesFor($user)))
            ->whereIn('job_postings.role_family', config('talent.collection.target_role_families'))
            ->whereNull('job_postings.ineligible_reason')
            ->where('first_run.started_at', '>=', PostingDay::windowStartsAt())
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

        $criteria = $override ?? ($user->jobPreference !== null
            ? PreferenceCriteria::fromPreference($user->jobPreference)
            : PreferenceCriteria::empty());

        if ($criteria->titles !== []) {
            $query->where(function (Builder $group) use ($criteria): void {
                foreach ($criteria->titles as $title) {
                    $pattern = WordPattern::toRegex($title);
                    $group->orWhereRaw(
                        '(job_postings.title ~* ? or job_posting_profiles.normalized_title ~* ?)',
                        [$pattern, $pattern],
                    );
                }
            });
        }

        if ($criteria->seniorities !== []) {
            $query->where(function (Builder $group) use ($criteria): void {
                $group->whereIn('job_posting_profiles.seniority', $criteria->seniorities);

                if (config('talent.matching.unknown_seniority_passes')) {
                    $group->orWhere('job_posting_profiles.seniority', 'unknown');
                }
            });
        }

        if ($criteria->stack !== []) {
            $placeholders = implode(', ', array_fill(0, count($criteria->stack), '?'));
            $query->whereRaw("job_posting_profiles.stack ??| array[{$placeholders}]::text[]", $criteria->stack);
        }

        $locations = $criteria->locations;

        if ($criteria->remoteMode === RemoteMode::RemoteOnly) {
            $query->where('job_posting_profiles.is_remote', true);
        } elseif ($locations !== []) {
            $orRemote = $criteria->remoteMode === RemoteMode::RemoteOrLocations;

            $query->where(function (Builder $group) use ($locations, $orRemote): void {
                if ($orRemote) {
                    $group->where('job_posting_profiles.is_remote', true);
                }

                self::orLocationMatch($group, $locations);
            });
        }

        foreach ($criteria->excludeWords as $word) {
            $pattern = WordPattern::toRegex($word);
            $stackValue = StackNormalizer::normalize([$word])[0] ?? mb_strtolower($word);

            $query->whereRaw(
                "not (job_postings.title ~* ? or coalesce(job_posting_profiles.normalized_title, '') ~* ? or job_posting_profiles.stack ?? ?)",
                [$pattern, $pattern, $stackValue],
            );
        }

        self::applyRestrictions($query, $criteria);

        return $query;
    }

    /**
     * Drops postings whose detected restrictions (job_postings.restrictions) the criteria
     * can't satisfy. Applies with empty criteria too.
     *
     * @param  Builder<JobPosting>  $query
     */
    private static function applyRestrictions(Builder $query, PreferenceCriteria $criteria): void
    {
        $locations = $criteria->locations;
        $namedRegions = self::regionsNamedBy($locations);

        $query->whereRaw(
            "not exists (select 1 from jsonb_array_elements(job_postings.restrictions) as r where r->>'kind' in ('region', 'work_authorization') and r->>'value' is not null and r->>'value' <> all(?::text[]))",
            ['{'.implode(',', array_map(fn (string $region): string => '"'.$region.'"', $namedRegions)).'}'],
        );

        $query->whereRaw(
            'not (job_postings.restrictions @> ?::jsonb)',
            [json_encode([['kind' => 'work_authorization', 'value' => null]])],
        );

        $query->where(function (Builder $group) use ($criteria, $locations): void {
            $group->whereRaw(
                'not (job_postings.restrictions @> ?::jsonb or job_postings.restrictions @> ?::jsonb)',
                [json_encode([['kind' => 'onsite']]), json_encode([['kind' => 'hybrid']])],
            );

            if ($criteria->remoteMode !== RemoteMode::RemoteOnly && $locations !== []) {
                $group->orWhere(fn (Builder $match) => self::orLocationMatch($match, $locations));
            }
        });
    }

    /**
     * Canonical regions (PostingRestrictions::REGION_ALIASES keys) that any of the given
     * locations names: a whole-word, case-insensitive match of one of the region's aliases.
     *
     * @param  list<string>  $locations
     * @return list<string>
     */
    private static function regionsNamedBy(array $locations): array
    {
        $regions = [];

        foreach (PostingRestrictions::REGION_ALIASES as $region => $aliases) {
            foreach ($aliases as $alias) {
                $pattern = '/(?<![\p{L}\p{N}])'.preg_quote($alias, '/').'(?![\p{L}\p{N}])/iu';

                foreach ($locations as $location) {
                    if (preg_match($pattern, $location) === 1) {
                        $regions[] = $region;

                        continue 3;
                    }
                }
            }
        }

        return $regions;
    }

    /**
     * ORs a whole-word match of each location against the posting location and the
     * profile's extracted locations.
     *
     * @param  Builder<JobPosting>  $group
     * @param  list<string>  $locations
     */
    private static function orLocationMatch(Builder $group, array $locations): void
    {
        foreach ($locations as $location) {
            $pattern = WordPattern::toRegex($location);
            $group->orWhereRaw(
                '(job_postings.location ~* ? or exists (select 1 from jsonb_array_elements_text(job_posting_profiles.locations) as l(v) where l.v ~* ?))',
                [$pattern, $pattern],
            );
        }
    }
}
