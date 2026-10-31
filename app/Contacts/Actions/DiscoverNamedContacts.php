<?php

namespace App\Contacts\Actions;

use App\Ai\Agents\ExtractCompanyPeople;
use App\Contacts\Support\CompanyPageFetcher;
use App\Contacts\Support\DnsLookup;
use App\Contacts\Support\HiringPowerTitle;
use App\Contacts\Support\NamedEmailCandidates;
use App\Contacts\Support\OutreachSuppressionList;
use App\Contacts\Support\SmtpProbe;
use App\Contacts\Support\SmtpVerdict;
use App\Enums\AiUsageStatus;
use App\Enums\ContactConfidence;
use App\Enums\ContactSource;
use App\Enums\OutreachStatus;
use App\Enums\SourceContactKind;
use App\Models\AiUsageRecord;
use App\Models\Company;
use App\Models\Contact;
use App\Models\JobPosting;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;
use UnexpectedValueException;

/**
 * Finds a named decision-maker for a verified, non-catch-all company and stores their address as a
 * tier-B contact only when SMTP accepts it and rejects a random address in the same session.
 * Unverified guesses are never stored. Names and addresses are never logged.
 */
class DiscoverNamedContacts
{
    /** Days before a company is checked for named contacts again. */
    public const int RECHECK_DAYS = 30;

    /** Names probed per company, so one session never asks about too many recipients. */
    private const int MAX_NAMES_PER_COMPANY = 3;

    private const int POSTINGS_PER_COMPANY = 5;

    public function __construct(
        private readonly CompanyPageFetcher $fetcher,
        private readonly SmtpProbe $smtp,
        private readonly DnsLookup $dns,
        private readonly OutreachSuppressionList $suppressions,
    ) {}

    public function handle(Company $company): void
    {
        if (! $this->isEligible($company)) {
            return;
        }

        $domain = strtolower(trim((string) $company->domain));
        $people = $this->people($company, $domain);

        // A page or agent failure stores nothing and leaves the company to be retried.
        if ($people === null) {
            return;
        }

        /** @var array<string, array{name: string, title: string|null}> $candidates email => person, in discovery order */
        $candidates = [];

        foreach ($people as $person) {
            foreach (NamedEmailCandidates::for($person['name'], $domain) as $email) {
                if (! isset($candidates[$email]) && ! $this->suppressions->covers($email)) {
                    $candidates[$email] = $person;
                }
            }
        }

        if ($candidates === []) {
            $this->markChecked($company);

            return;
        }

        $host = $this->dns->mxHosts($domain)[0] ?? $domain;
        $randomAddress = 'nonexistent-'.Str::lower(Str::random(8)).'@'.$domain;
        $result = $this->smtp->probe($host, [$randomAddress, ...array_keys($candidates)]);
        $randomCode = $result->codes[$randomAddress] ?? null;
        $sessionUsable = $result->connected && $result->sessionOk;

        if (! $sessionUsable || SmtpVerdict::rejectsMailbox($randomCode, $result->replies[$randomAddress] ?? null) !== true) {
            // A random address accepted in a sound session behaves as catch-all: conclusive, nothing verifiable.
            if ($sessionUsable && SmtpVerdict::isAccepted($randomCode)) {
                $this->markChecked($company);
            }

            return;
        }

        foreach ($candidates as $email => $person) {
            if (SmtpVerdict::isAccepted($result->codes[$email] ?? null)) {
                Contact::query()->updateOrCreate([
                    'company_id' => $company->id,
                    'source' => ContactSource::Named,
                    'email' => $email,
                ], [
                    'local_part' => Str::before($email, '@'),
                    'person_name' => $person['name'],
                    'person_title' => $person['title'],
                    'confidence' => ContactConfidence::SmtpVerified,
                    'checked_at' => now(),
                    'job_posting_id' => null,
                ]);

                break;
            }
        }

        $this->markChecked($company);
    }

    public function isEligible(Company $company): bool
    {
        return $company->outreach_status === OutreachStatus::Verified
            && $company->domain !== null
            && $company->is_catch_all === false
            && ! $this->suppressions->coversDomain($company->domain)
            && ($company->named_contacts_checked_at === null
                || $company->named_contacts_checked_at->lessThan(now()->subDays(self::RECHECK_DAYS)));
    }

    private function markChecked(Company $company): void
    {
        $company->forceFill(['named_contacts_checked_at' => now()])->save();
    }

    /**
     * Up to three distinct people with hiring power, in discovery order, or null when a call failed.
     *
     * @return list<array{name: string, title: string|null}>|null
     */
    private function people(Company $company, string $domain): ?array
    {
        $postings = JobPosting::query()
            ->where('company_id', $company->id)
            ->latest('last_seen_at')
            ->limit(self::POSTINGS_PER_COMPANY)
            ->get(['id', 'source_id', 'source_contacts', 'description_html', 'description_text']);

        $people = [];

        foreach ($postings as $posting) {
            foreach ($posting->source_contacts ?? [] as $contact) {
                $kind = $contact['kind'];
                $name = trim((string) $contact['name']);

                if ($name !== '' && in_array($kind, [SourceContactKind::Founder->value, SourceContactKind::HiringManager->value], true)) {
                    $people[] = ['name' => $name, 'title' => null];
                }
            }
        }

        try {
            $pages = $this->fetcher->fetch($domain);
        } catch (Throwable) {
            return null;
        }

        $posting = $postings->first();
        $postingText = $posting === null ? null : ($posting->description_html ?? $posting->description_text);

        if ($pages !== [] || filled($postingText)) {
            $extracted = $this->extractPeople($company, $pages, $postingText);

            if ($extracted === null) {
                return null;
            }

            foreach ($extracted as $person) {
                if (HiringPowerTitle::matches($person['title'])) {
                    $people[] = $person;
                }
            }
        }

        $unique = [];

        foreach ($people as $person) {
            $unique[$person['name']] ??= $person;
        }

        return array_slice(array_values($unique), 0, self::MAX_NAMES_PER_COMPANY);
    }

    /**
     * @param  array<string, string>  $pages
     * @return list<array{name: string, title: string|null}>|null null when the agent call failed
     */
    private function extractPeople(Company $company, array $pages, ?string $postingText): ?array
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
            // Never logged: the context holds personal data.
            $status = AiUsageStatus::Failed;
            $people = null;
        }

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
}
