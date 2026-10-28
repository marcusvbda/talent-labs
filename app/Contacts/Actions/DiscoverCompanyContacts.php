<?php

namespace App\Contacts\Actions;

use App\Ai\Jobs\ExtractJobPostingProfileJob;
use App\Contacts\Support\DnsLookup;
use App\Contacts\Support\SmtpProbe;
use App\Contacts\Support\SmtpProbeResult;
use App\Enums\ContactConfidence;
use App\Enums\ContactStatus;
use App\Enums\DomainStatus;
use App\Enums\OutreachStatus;
use App\Models\Company;
use Illuminate\Support\Str;

class DiscoverCompanyContacts
{
    /**
     * Role mailbox local parts tried for every company.
     */
    public const array ALIASES = ['careers', 'jobs', 'hr', 'talent', 'recruiting', 'people'];

    /**
     * Reply text that marks a rejection as a block/policy decision about us, not about the mailbox.
     */
    private const string BLOCK_PATTERN = '/spamhaus|blocked|blacklist|blocklist|policy|reputation|dnsbl|not allowed/i';

    public function __construct(
        private DnsLookup $dns,
        private SmtpProbe $smtp,
    ) {}

    /**
     * Probe the company's mail server for role aliases in a single handshake-only
     * session and store the ones that can be addressed, with their confidence.
     */
    public function handle(Company $company): void
    {
        $domain = (string) $company->domain;

        if ($company->domain_status === DomainStatus::Pending) {
            // DNS lookup failed: no verdict yet, retried after the cooldown.
            $this->markChecked($company);

            return;
        }

        if ($company->domain_status !== DomainStatus::Found || $domain === '') {
            $company->contact_status = ContactStatus::NoDomain;
            $company->outreach_status = OutreachStatus::NoDomain;
            $company->contact_checked_at = now();
            $company->save();

            return;
        }

        if ($company->contact_status !== ContactStatus::Pending) {
            return;
        }

        $host = $this->dns->mxHosts($domain)[0] ?? $domain;

        $probeAddress = 'nonexistent-'.Str::lower(Str::random(8)).'@'.$domain;

        $aliasAddresses = [];

        foreach (self::ALIASES as $alias) {
            $aliasAddresses[$alias] = "{$alias}@{$domain}";
        }

        $result = $this->smtp->probe($host, [$probeAddress, ...array_values($aliasAddresses)]);

        $isCatchAll = $this->catchAllVerdict($result, $probeAddress);

        if ($isCatchAll === null) {
            // Blocked, greylisted or no session: says nothing about the mailboxes, so no verdict yet.
            $this->markInconclusive($company);

            return;
        }

        $stored = 0;
        $verified = false;

        foreach ($aliasAddresses as $alias => $address) {
            $confidence = $this->confidenceFor($isCatchAll, $result->codes[$address] ?? null);

            if ($confidence === null) {
                continue;
            }

            $company->contacts()->updateOrCreate(
                ['local_part' => $alias],
                ['email' => $address, 'confidence' => $confidence, 'checked_at' => now()],
            );

            $stored++;

            if ($confidence === ContactConfidence::SmtpVerified) {
                $verified = true;
            }
        }

        $company->is_catch_all = $isCatchAll;
        $company->contact_status = $stored > 0 ? ContactStatus::Found : ContactStatus::NotFound;
        $company->outreach_status = $verified ? OutreachStatus::Verified : OutreachStatus::NotVerifiable;
        $company->contact_checked_at = now();
        $company->save();

        if ($company->outreach_status === OutreachStatus::Verified) {
            $company->jobPostings()->needingProfile()->pluck('id')
                ->each(fn (int $id) => ExtractJobPostingProfileJob::dispatch($id));
        }
    }

    /**
     * True when the server accepts the random probe address (catch-all), false when it rejects it
     * as an unknown user, null when the outcome is inconclusive (no session, 4xx, block/policy).
     */
    private function catchAllVerdict(SmtpProbeResult $result, string $probeAddress): ?bool
    {
        $code = $result->codes[$probeAddress] ?? null;

        if (! $result->connected || ! $result->sessionOk || $code === null) {
            return null;
        }

        if ($this->isAccepted($code)) {
            return true;
        }

        if ($code < 500 || $code >= 600) {
            return null;
        }

        $text = (string) ($result->replies[$probeAddress] ?? '');

        // Enhanced status first: 5.7.x is a policy decision about us, while 5.1.x and Exchange's 5.4.1
        // ("Recipient address rejected: Access denied") are plain unknown-mailbox answers.
        if (preg_match('/^5\.7\.\d+/', $text) === 1) {
            return null;
        }

        if (preg_match('/^5\.(?:1\.\d+|4\.1)\b/', $text) === 1) {
            return false;
        }

        return preg_match(self::BLOCK_PATTERN, $text) === 1 ? null : false;
    }

    /**
     * Records the attempt without a verdict: statuses stay pending.
     */
    private function markChecked(Company $company): void
    {
        if ($company->contact_checked_at === null) {
            $company->contact_checked_at = now();
            $company->save();
        }
    }

    /**
     * Inconclusive probe. The first one starts the clock; once the company has stayed inconclusive for
     * RunCompanyDiscovery::PENDING_GIVE_UP_DAYS it is closed as not verifiable (`contacts:recheck` can reopen it).
     */
    private function markInconclusive(Company $company): void
    {
        if ($company->contact_checked_at === null) {
            $this->markChecked($company);

            return;
        }

        if ($company->contact_checked_at->greaterThan(now()->subDays(RunCompanyDiscovery::PENDING_GIVE_UP_DAYS))) {
            return;
        }

        $company->contact_status = ContactStatus::NotFound;
        $company->outreach_status = OutreachStatus::NotVerifiable;
        $company->save();
    }

    /**
     * Confidence to store for an alias, or null when the alias must be skipped.
     */
    private function confidenceFor(bool $isCatchAll, ?int $code): ?ContactConfidence
    {
        if (! $this->isAccepted($code)) {
            return null;
        }

        return $isCatchAll ? ContactConfidence::CatchAll : ContactConfidence::SmtpVerified;
    }

    private function isAccepted(?int $code): bool
    {
        return $code !== null && $code >= 200 && $code < 300;
    }
}
