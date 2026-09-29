<?php

namespace App\Reports;

use App\Client\LanguageUnlockCounts;
use App\Enums\ContactConfidence;
use App\Enums\OutreachStatus;
use App\Enums\ProfileStatus;
use App\Models\ApplicationProfile;
use App\Models\User;
use App\Outreach\Queries\MatchingJobPostings;
use App\Plans\PlanCatalog;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-type FunnelCounts array{
 *     fetched: int, new: int, postings: int,
 *     target_family: int, with_company: int, with_domain: int,
 *     verified: int, profile_done: int,
 *     lang: array{en: int, pt: int, other: int}
 * }
 * @phpstan-type SourceRow array{
 *     id: int, name: string, adapter: string, is_active: bool,
 *     fetched: int, new: int, postings: int,
 *     target_family: int, with_company: int, with_domain: int,
 *     verified: int, profile_done: int,
 *     lang: array{en: int, pt: int, other: int}
 * }
 * @phpstan-type GlobalStock array{
 *     companies_by_outreach_status: array{pending: int, no_domain: int, not_verifiable: int, verified: int, total: int},
 *     contacts_by_confidence: array{smtp_verified: int, catch_all: int, mx_only: int, total: int},
 *     mx_only_share: float,
 *     port25_warning: bool
 * }
 * @phpstan-type UserRunway array{
 *     id: int, name: string, plan: string, daily_limit: int,
 *     active_languages: list<string>,
 *     pool: array{en: int, pt: int, total: int},
 *     runway_days: float
 * }
 * @phpstan-type Funnel array{
 *     generated_at: string,
 *     window_days: int,
 *     since: string,
 *     sources: list<SourceRow>,
 *     totals: FunnelCounts,
 *     global: GlobalStock,
 *     user: UserRunway|null
 * }
 */
final class InventoryFunnel
{
    /**
     * Above this share of mx_only contacts, SMTP probing is most likely failing (port 25 blocked).
     */
    private const float PORT25_MX_ONLY_THRESHOLD = 0.8;

    public function __construct(private readonly PlanCatalog $plans) {}

    /**
     * Read-only funnel from collection to a client's verified pool: per-source counts over
     * the last $days (runs by started_at, postings by first_seen_at), the all-time company
     * and contact stock, and, when a user is given, their pool and runway in days.
     *
     * @return Funnel
     */
    public function build(int $days, ?User $user = null): array
    {
        $now = now();
        $since = $now->copy()->subDays($days);

        $sources = $this->sources($since);

        return [
            'generated_at' => $now->toIso8601String(),
            'window_days' => $days,
            'since' => $since->toIso8601String(),
            'sources' => $sources,
            'totals' => $this->totals($sources),
            'global' => $this->global(),
            'user' => $user === null ? null : $this->user($user),
        ];
    }

    /**
     * @return list<SourceRow>
     */
    private function sources(CarbonInterface $since): array
    {
        $runs = DB::table('source_runs')
            ->select('source_id')
            ->selectRaw('coalesce(sum(jobs_fetched), 0) as fetched, coalesce(sum(jobs_new), 0) as new')
            ->where('started_at', '>=', $since)
            ->groupBy('source_id')
            ->get()
            ->keyBy('source_id');

        $done = ProfileStatus::Done->value;
        $families = array_values(config('talent.collection.target_role_families', []));
        $familyFilter = $families === []
            ? 'false'
            : 'job_postings.role_family in ('.implode(', ', array_fill(0, count($families), '?')).')';

        // The profile is unique per posting, so the left joins never multiply rows.
        $postings = DB::table('job_postings')
            ->leftJoin('companies', 'companies.id', '=', 'job_postings.company_id')
            ->leftJoin('job_posting_profiles', 'job_posting_profiles.job_posting_id', '=', 'job_postings.id')
            ->select('job_postings.source_id')
            ->selectRaw('count(*) as postings')
            ->selectRaw("count(*) filter (where {$familyFilter}) as target_family", $families)
            ->selectRaw('count(*) filter (where job_postings.company_id is not null) as with_company')
            ->selectRaw('count(*) filter (where companies.domain is not null) as with_domain')
            ->selectRaw('count(*) filter (where companies.outreach_status = ?) as verified', [OutreachStatus::Verified->value])
            ->selectRaw('count(*) filter (where job_posting_profiles.status = ?) as profile_done', [$done])
            ->selectRaw("count(*) filter (where job_posting_profiles.status = ? and job_posting_profiles.language = 'en') as lang_en", [$done])
            ->selectRaw("count(*) filter (where job_posting_profiles.status = ? and job_posting_profiles.language = 'pt') as lang_pt", [$done])
            ->selectRaw("count(*) filter (where job_posting_profiles.status = ? and job_posting_profiles.language not in ('en', 'pt')) as lang_other", [$done])
            ->where('job_postings.first_seen_at', '>=', $since)
            ->groupBy('job_postings.source_id')
            ->get()
            ->keyBy('source_id');

        return array_values(DB::table('sources')
            ->select('id', 'name', 'adapter', 'is_active')
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(function (object $source) use ($runs, $postings): array {
                $run = $runs->get($source->id);
                $posting = $postings->get($source->id);

                return [
                    'id' => (int) $source->id,
                    'name' => (string) $source->name,
                    'adapter' => (string) $source->adapter,
                    'is_active' => (bool) $source->is_active,
                    'fetched' => (int) ($run->fetched ?? 0),
                    'new' => (int) ($run->new ?? 0),
                    'postings' => (int) ($posting->postings ?? 0),
                    'target_family' => (int) ($posting->target_family ?? 0),
                    'with_company' => (int) ($posting->with_company ?? 0),
                    'with_domain' => (int) ($posting->with_domain ?? 0),
                    'verified' => (int) ($posting->verified ?? 0),
                    'profile_done' => (int) ($posting->profile_done ?? 0),
                    'lang' => [
                        'en' => (int) ($posting->lang_en ?? 0),
                        'pt' => (int) ($posting->lang_pt ?? 0),
                        'other' => (int) ($posting->lang_other ?? 0),
                    ],
                ];
            })
            ->all());
    }

    /**
     * @param  list<SourceRow>  $sources
     * @return FunnelCounts
     */
    private function totals(array $sources): array
    {
        $totals = [
            'fetched' => 0, 'new' => 0, 'postings' => 0,
            'target_family' => 0, 'with_company' => 0, 'with_domain' => 0,
            'verified' => 0, 'profile_done' => 0,
            'lang' => ['en' => 0, 'pt' => 0, 'other' => 0],
        ];

        foreach ($sources as $source) {
            foreach (['fetched', 'new', 'postings', 'target_family', 'with_company', 'with_domain', 'verified', 'profile_done'] as $key) {
                $totals[$key] += $source[$key];
            }

            foreach (['en', 'pt', 'other'] as $language) {
                $totals['lang'][$language] += $source['lang'][$language];
            }
        }

        return $totals;
    }

    /**
     * All-time stock, not windowed.
     *
     * @return GlobalStock
     */
    private function global(): array
    {
        /** @var array<string, int|string> $statusCounts */
        $statusCounts = DB::table('companies')
            ->select('outreach_status')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('outreach_status')
            ->pluck('aggregate', 'outreach_status')
            ->all();

        /** @var array<string, int|string> $confidenceCounts */
        $confidenceCounts = DB::table('contacts')
            ->select('confidence')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('confidence')
            ->pluck('aggregate', 'confidence')
            ->all();

        $companies = [
            'pending' => (int) ($statusCounts[OutreachStatus::Pending->value] ?? 0),
            'no_domain' => (int) ($statusCounts[OutreachStatus::NoDomain->value] ?? 0),
            'not_verifiable' => (int) ($statusCounts[OutreachStatus::NotVerifiable->value] ?? 0),
            'verified' => (int) ($statusCounts[OutreachStatus::Verified->value] ?? 0),
        ];
        $companies['total'] = array_sum($companies);

        $contacts = [
            'smtp_verified' => (int) ($confidenceCounts[ContactConfidence::SmtpVerified->value] ?? 0),
            'catch_all' => (int) ($confidenceCounts[ContactConfidence::CatchAll->value] ?? 0),
            'mx_only' => (int) ($confidenceCounts[ContactConfidence::MxOnly->value] ?? 0),
        ];
        $contacts['total'] = array_sum($contacts);

        $mxOnlyShare = $contacts['total'] > 0 ? $contacts['mx_only'] / $contacts['total'] : 0.0;

        return [
            'companies_by_outreach_status' => $companies,
            'contacts_by_confidence' => $contacts,
            'mx_only_share' => $mxOnlyShare,
            'port25_warning' => $contacts['total'] > 0 && $mxOnlyShare > self::PORT25_MX_ONLY_THRESHOLD,
        ];
    }

    /**
     * @return UserRunway
     */
    private function user(User $user): array
    {
        $plan = $this->plans->for($user);
        $byLanguage = LanguageUnlockCounts::forUser($user);

        $total = (int) MatchingJobPostings::forUser($user)
            ->reorder()
            ->toBase()
            ->select(DB::raw('count(distinct job_postings.company_id) as aggregate'))
            ->value('aggregate');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'plan' => $plan->name,
            'daily_limit' => $plan->dailyLimit,
            'active_languages' => ApplicationProfile::activeCompleteLanguagesFor($user),
            'pool' => [
                'en' => $byLanguage['en'],
                'pt' => $byLanguage['pt'],
                'total' => $total,
            ],
            'runway_days' => $plan->dailyLimit > 0 ? round($total / $plan->dailyLimit, 1) : 0.0,
        ];
    }
}
