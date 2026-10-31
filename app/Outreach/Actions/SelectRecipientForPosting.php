<?php

namespace App\Outreach\Actions;

use App\Contacts\Support\OutreachSuppressionList;
use App\Enums\ContactConfidence;
use App\Enums\ContactSource;
use App\Models\Application;
use App\Models\Contact;
use App\Models\JobPosting;

class SelectRecipientForPosting
{
    public function __construct(
        private SelectRecipientForCompany $companyRecipients,
        private OutreachSuppressionList $suppressions,
    ) {}

    /**
     * Tier A first: an email published in this posting (never another posting's), not
     * rejected, by id, skipping any address that already reached today's cap across all
     * clients (talent.outreach.tier_a_daily_cap). When the company is verified, tier B
     * (first smtp_verified named contact by id) and then tier C (SelectRecipientForCompany).
     * The cap is not locked across clients, so it may be exceeded by a few under concurrency.
     * In every tier, an address covered by the suppression list is skipped; a covered
     * tier-C result means no recipient.
     */
    public function handle(JobPosting $posting): ?Contact
    {
        $cap = (int) config('talent.outreach.tier_a_daily_cap');

        $published = $posting->contacts()
            ->where('source', ContactSource::Posting)
            ->where('confidence', '!=', ContactConfidence::Rejected)
            ->orderBy('id')
            ->get();

        foreach ($published as $contact) {
            if ($this->suppressions->covers($contact->email)) {
                continue;
            }

            $sentToday = Application::query()
                ->countedToday()
                ->where('recipient_email', $contact->email)
                ->count();

            if ($sentToday < $cap) {
                return $contact;
            }
        }

        $company = $posting->company;

        if ($company === null || ! $company->isVerifiedForSending()) {
            return null;
        }

        $named = $company->contacts()
            ->where('source', ContactSource::Named)
            ->where('confidence', ContactConfidence::SmtpVerified)
            ->orderBy('id')
            ->get()
            ->first(fn (Contact $contact): bool => ! $this->suppressions->covers($contact->email));

        if ($named !== null) {
            return $named;
        }

        $alias = $this->companyRecipients->handle($company);

        return $alias !== null && ! $this->suppressions->covers($alias->email) ? $alias : null;
    }
}
