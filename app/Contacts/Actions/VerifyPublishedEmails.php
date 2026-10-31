<?php

namespace App\Contacts\Actions;

use App\Contacts\Support\DnsLookup;
use App\Contacts\Support\OutreachSuppressionList;
use App\Contacts\Support\SmtpProbe;
use App\Contacts\Support\SmtpProbeResult;
use App\Contacts\Support\SmtpVerdict;
use App\Enums\ContactConfidence;
use App\Enums\ContactSource;
use App\Models\Company;
use App\Models\Contact;
use Illuminate\Support\Str;

class VerifyPublishedEmails
{
    /**
     * A stored verdict younger than this is reused instead of probing again.
     */
    public const int VERDICT_TTL_DAYS = 30;

    /**
     * No new SMTP session is opened once this much time has passed since the call started.
     */
    public const int SESSION_START_BUDGET_SECONDS = 25;

    public function __construct(
        private DnsLookup $dns,
        private SmtpProbe $smtp,
        private OutreachSuppressionList $suppressions,
    ) {}

    /**
     * Verdict for each email published in a posting of the company: a stored verdict within the TTL,
     * otherwise one handshake-only session per email domain. Suppressed emails are left out.
     *
     * @param  list<string>  $emails
     * @return array<string, ContactConfidence>
     */
    public function handle(Company $company, array $emails): array
    {
        $startedAt = microtime(true);

        $emails = array_values(array_unique(array_filter($emails, fn (string $email): bool => str_contains($email, '@'))));

        // Suppressed addresses or domains get no probe and no verdict, so they never become sendable.
        $emails = array_values(array_filter($emails, fn (string $email): bool => ! $this->suppressions->covers($email)));

        if ($emails === []) {
            return [];
        }

        $verdicts = $this->storedVerdicts($company, $emails);

        $byDomain = [];

        foreach ($emails as $email) {
            if (! isset($verdicts[$email])) {
                $byDomain[Str::lower(Str::afterLast($email, '@'))][] = $email;
            }
        }

        foreach ($byDomain as $domain => $domainEmails) {
            if (microtime(true) - $startedAt >= self::SESSION_START_BUDGET_SECONDS) {
                foreach ($domainEmails as $email) {
                    $verdicts[$email] = ContactConfidence::Inconclusive;
                }

                continue;
            }

            $verdicts = [...$verdicts, ...$this->probeDomain((string) $domain, $domainEmails)];
        }

        return $verdicts;
    }

    /**
     * Fresh posting verdicts already stored for the company, latest check first.
     *
     * @param  list<string>  $emails
     * @return array<string, ContactConfidence>
     */
    private function storedVerdicts(Company $company, array $emails): array
    {
        $verdicts = [];

        $company->contacts()
            ->where('source', ContactSource::Posting)
            ->whereIn('email', $emails)
            ->where('checked_at', '>=', now()->subDays(self::VERDICT_TTL_DAYS))
            ->orderByDesc('checked_at')
            ->get(['email', 'confidence'])
            ->each(function (Contact $contact) use (&$verdicts): void {
                $verdicts[$contact->email] ??= $contact->confidence;
            });

        return $verdicts;
    }

    /**
     * One session for the domain's mail server: a random address first (catch-all check), then the emails.
     *
     * @param  list<string>  $emails
     * @return array<string, ContactConfidence>
     */
    private function probeDomain(string $domain, array $emails): array
    {
        $host = $this->dns->mxHosts($domain)[0] ?? $domain;

        $probeAddress = 'nonexistent-'.Str::lower(Str::random(8)).'@'.$domain;

        $result = $this->smtp->probe($host, [$probeAddress, ...$emails]);

        $randomVerdict = $this->randomVerdict($result, $probeAddress);

        $verdicts = [];

        foreach ($emails as $email) {
            $verdicts[$email] = $this->confidenceFor($result, $email, $randomVerdict);
        }

        return $verdicts;
    }

    /**
     * True when the server accepts the random address (catch-all), false when it rejects it
     * as an unknown user, null when the outcome is inconclusive.
     */
    private function randomVerdict(SmtpProbeResult $result, string $probeAddress): ?bool
    {
        $code = $result->codes[$probeAddress] ?? null;

        if ($code === null) {
            return null;
        }

        if (SmtpVerdict::isAccepted($code)) {
            return true;
        }

        return SmtpVerdict::rejectsMailbox($code, $result->replies[$probeAddress] ?? null) === true ? false : null;
    }

    private function confidenceFor(SmtpProbeResult $result, string $email, ?bool $randomVerdict): ContactConfidence
    {
        $code = $result->codes[$email] ?? null;

        if (! $result->connected || ! $result->sessionOk || $code === null) {
            return ContactConfidence::Inconclusive;
        }

        if (SmtpVerdict::isAccepted($code)) {
            return match ($randomVerdict) {
                false => ContactConfidence::SmtpVerified,
                true => ContactConfidence::CatchAll,
                null => ContactConfidence::Inconclusive,
            };
        }

        return SmtpVerdict::rejectsMailbox($code, $result->replies[$email] ?? null) === true
            ? ContactConfidence::Rejected
            : ContactConfidence::Inconclusive;
    }
}
