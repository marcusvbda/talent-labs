<?php

namespace App\Reports;

use App\Ai\Agents\ExtractCompanyPeople;
use App\Contacts\Support\CompanyPageFetcher;
use App\Contacts\Support\DnsLookup;
use App\Contacts\Support\HiringPowerTitle;
use App\Contacts\Support\NamedEmailCandidates;
use App\Contacts\Support\OutreachSuppressionList;
use App\Contacts\Support\SmtpProbe;
use App\Contacts\Support\SmtpVerdict;
use App\Enums\AiUsageStatus;
use App\Enums\OutreachStatus;
use App\Enums\SourceContactKind;
use App\Models\AiUsageRecord;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\Source;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;
use UnexpectedValueException;

/**
 * Read-only estimate of how often a named, verifiable contact could be found for verified
 * companies. Writes nothing except ai_usage_records for the agent calls; names and addresses only
 * live in memory and never reach the returned aggregates.
 *
 * @phpstan-type Counts array{companies: int, with_name: int, with_verified_named_email: int, catch_all: int, probed: int, inconclusive: int}
 * @phpstan-type Report array{
 *     sample_requested: int,
 *     sources: list<array{id: int, name: string, companies: int, with_name: int, with_verified_named_email: int, catch_all: int, probed: int, inconclusive: int}>,
 *     totals: array{companies: int, with_name: int, with_verified_named_email: int, catch_all: int, probed: int, inconclusive: int, with_name_share: float, with_verified_named_email_share: float, catch_all_share: float, inconclusive_share: float},
 *     tokens: array{agent_calls: int, failed_calls: int, input: int, output: int},
 *     usd: float|null,
 *     port25_warning: bool,
 * }
 */
class NamedContactCoverage
{
    /** Names probed per company, so one session never asks about too many recipients. */
    private const int MAX_NAMES_PER_COMPANY = 3;

    private const int POSTINGS_PER_COMPANY = 5;

    public function __construct(
        private readonly CompanyPageFetcher $fetcher,
        private readonly SmtpProbe $smtp,
        private readonly DnsLookup $dns,
        private readonly OutreachSuppressionList $suppressions,
    ) {}

    /**
     * @return Report
     */
    public function build(int $sample, ?float $inputUsdPerMtok = null, ?float $outputUsdPerMtok = null): array
    {
        $tokens = ['agent_calls' => 0, 'failed_calls' => 0, 'input' => 0, 'output' => 0];
        $sourceNames = Source::query()->pluck('name', 'id');

        /** @var array<int, Counts> $perSource */
        $perSource = [];

        foreach ($this->sample($sample) as [$sourceId, $company]) {
            $counts = $perSource[$sourceId] ?? $this->emptyCounts();
            $counts['companies']++;

            $outcome = $this->measure($company, $sourceId, $tokens);

            $counts['with_name'] += (int) $outcome['with_name'];
            $counts['with_verified_named_email'] += (int) $outcome['verified'];
            $counts['catch_all'] += (int) ($company->is_catch_all === true);
            $counts['probed'] += (int) $outcome['probed'];
            $counts['inconclusive'] += (int) $outcome['inconclusive'];

            $perSource[$sourceId] = $counts;
        }

        ksort($perSource);

        $totals = $this->emptyCounts();
        $sources = [];

        foreach ($perSource as $sourceId => $counts) {
            foreach ($counts as $key => $value) {
                $totals[$key] += $value;
            }

            $sources[] = ['id' => $sourceId, 'name' => (string) ($sourceNames[$sourceId] ?? "#{$sourceId}"), ...$counts];
        }

        $usd = $inputUsdPerMtok !== null && $outputUsdPerMtok !== null
            ? round($tokens['input'] / 1_000_000 * $inputUsdPerMtok + $tokens['output'] / 1_000_000 * $outputUsdPerMtok, 6)
            : null;

        return [
            'sample_requested' => $sample,
            'sources' => $sources,
            'totals' => [
                ...$totals,
                'with_name_share' => $this->share($totals['with_name'], $totals['companies']),
                'with_verified_named_email_share' => $this->share($totals['with_verified_named_email'], $totals['companies']),
                'catch_all_share' => $this->share($totals['catch_all'], $totals['companies']),
                'inconclusive_share' => $this->share($totals['inconclusive'], $totals['probed']),
            ],
            'tokens' => $tokens,
            'usd' => $usd,
            'port25_warning' => $totals['probed'] > 0 && $totals['inconclusive'] === $totals['probed'],
        ];
    }

    /**
     * Verified companies with a known, unsuppressed domain, taken round-robin across the sources
     * of their postings and in random order within a source. Each company is sampled once.
     *
     * @return list<array{int, Company}>
     */
    private function sample(int $size): array
    {
        $pairs = JobPosting::query()
            ->join('companies', 'companies.id', '=', 'job_postings.company_id')
            ->where('companies.outreach_status', OutreachStatus::Verified->value)
            ->whereNotNull('companies.domain')
            ->distinct()
            ->get(['job_postings.source_id', 'job_postings.company_id']);

        /** @var array<int, list<int>> $bySource */
        $bySource = [];

        foreach ($pairs as $pair) {
            $bySource[(int) $pair->source_id][] = (int) $pair->company_id;
        }

        ksort($bySource);
        $bySource = array_map(function (array $ids): array {
            shuffle($ids);

            return $ids;
        }, $bySource);

        $picked = [];
        $seen = [];

        while (count($picked) < $size && $bySource !== []) {
            foreach (array_keys($bySource) as $sourceId) {
                $companyId = array_shift($bySource[$sourceId]);

                if ($bySource[$sourceId] === []) {
                    unset($bySource[$sourceId]);
                }

                if ($companyId === null || isset($seen[$companyId])) {
                    continue;
                }

                $seen[$companyId] = true;
                $company = Company::query()->find($companyId);

                if ($company === null || $company->domain === null || $this->suppressions->coversDomain($company->domain)) {
                    continue;
                }

                $picked[] = [$sourceId, $company];

                if (count($picked) >= $size) {
                    break;
                }
            }
        }

        return $picked;
    }

    /**
     * @param  array{agent_calls: int, failed_calls: int, input: int, output: int}  $tokens
     * @return array{with_name: bool, verified: bool, probed: bool, inconclusive: bool}
     */
    private function measure(Company $company, int $sourceId, array &$tokens): array
    {
        $domain = strtolower(trim((string) $company->domain));

        $postings = JobPosting::query()
            ->where('company_id', $company->id)
            ->orderByRaw('source_id = ? desc', [$sourceId])
            ->latest('last_seen_at')
            ->limit(self::POSTINGS_PER_COMPANY)
            ->get(['id', 'source_id', 'source_contacts', 'description_html', 'description_text']);

        $names = [];

        foreach ($postings as $posting) {
            foreach ($posting->source_contacts ?? [] as $contact) {
                $kind = $contact['kind'];
                $name = trim((string) $contact['name']);

                if ($name !== '' && in_array($kind, [SourceContactKind::Founder->value, SourceContactKind::HiringManager->value], true)) {
                    $names[] = $name;
                }
            }
        }

        $posting = $postings->first();
        $pages = $this->fetcher->fetch($domain);
        $postingText = $posting === null ? null : ($posting->description_html ?? $posting->description_text);

        if ($pages !== [] || filled($postingText)) {
            foreach ($this->extractPeople($company, $pages, $postingText, $tokens) as $person) {
                if (HiringPowerTitle::matches($person['title'])) {
                    $names[] = $person['name'];
                }
            }
        }

        $names = array_slice(array_values(array_unique($names)), 0, self::MAX_NAMES_PER_COMPANY);
        $outcome = ['with_name' => $names !== [], 'verified' => false, 'probed' => false, 'inconclusive' => false];

        if ($names === [] || $company->is_catch_all !== false) {
            return $outcome;
        }

        $candidates = array_values(array_unique(array_merge(...array_map(
            fn (string $name): array => NamedEmailCandidates::for($name, $domain),
            $names,
        ))));
        $candidates = array_values(array_filter($candidates, fn (string $email): bool => ! $this->suppressions->covers($email)));

        if ($candidates === []) {
            return $outcome;
        }

        $host = $this->dns->mxHosts($domain)[0] ?? $domain;
        $randomAddress = 'nonexistent-'.Str::lower(Str::random(8)).'@'.$domain;
        $result = $this->smtp->probe($host, [$randomAddress, ...$candidates]);
        $randomCode = $result->codes[$randomAddress] ?? null;

        $outcome['probed'] = true;

        $randomRejected = $result->connected && $result->sessionOk
            && SmtpVerdict::rejectsMailbox($randomCode, $result->replies[$randomAddress] ?? null) === true;

        if (! $randomRejected) {
            // A random address that is accepted now behaves as catch-all: no verdict, but not inconclusive.
            $outcome['inconclusive'] = ! ($result->connected && $result->sessionOk && SmtpVerdict::isAccepted($randomCode));

            return $outcome;
        }

        foreach ($candidates as $candidate) {
            if (SmtpVerdict::isAccepted($result->codes[$candidate] ?? null)) {
                $outcome['verified'] = true;

                break;
            }
        }

        return $outcome;
    }

    /**
     * @param  array<string, string>  $pages
     * @param  array{agent_calls: int, failed_calls: int, input: int, output: int}  $tokens
     * @return list<array{name: string, title: string|null}>
     */
    private function extractPeople(Company $company, array $pages, ?string $postingText, array &$tokens): array
    {
        $agent = new ExtractCompanyPeople($company->name, $pages, $postingText);
        $startedAt = hrtime(true);
        $usage = null;
        $status = AiUsageStatus::Completed;
        $people = [];

        try {
            $response = $agent->prompt($agent->context());

            if (! $response instanceof StructuredAgentResponse) {
                throw new UnexpectedValueException('The company people agent did not return structured output.');
            }

            $usage = $response->usage;

            foreach ((array) ($response->toArray()['people'] ?? []) as $person) {
                $name = is_array($person) && is_string($person['name'] ?? null) ? trim($person['name']) : '';

                if ($name !== '') {
                    $title = is_string($person['title'] ?? null) ? trim($person['title']) : null;
                    $people[] = ['name' => $name, 'title' => $title];
                }
            }
        } catch (Throwable) {
            // Never logged: the context holds personal data. Counted as a failed call instead.
            $status = AiUsageStatus::Failed;
            $tokens['failed_calls']++;
        }

        $tokens['agent_calls']++;
        $tokens['input'] += $usage->promptTokens ?? 0;
        $tokens['output'] += $usage->completionTokens ?? 0;

        AiUsageRecord::query()->create([
            'agent' => ExtractCompanyPeople::CACHE_KEY,
            'model' => ExtractCompanyPeople::MODEL,
            'job_posting_id' => null,
            'input_tokens' => $usage->promptTokens ?? 0,
            'output_tokens' => $usage->completionTokens ?? 0,
            'cache_hit' => false,
            'duration_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
            'status' => $status,
        ]);

        return $people;
    }

    /**
     * @return Counts
     */
    private function emptyCounts(): array
    {
        return ['companies' => 0, 'with_name' => 0, 'with_verified_named_email' => 0, 'catch_all' => 0, 'probed' => 0, 'inconclusive' => 0];
    }

    private function share(int $count, int $of): float
    {
        return $of > 0 ? round($count / $of, 4) : 0.0;
    }
}
