<?php

namespace App\Outreach\Actions;

use App\Enums\ContactConfidence;
use App\Enums\ContactSource;
use App\Models\Application;
use App\Models\Contact;
use App\Models\JobPosting;

class SelectRecipientForPosting
{
    public function __construct(private SelectRecipientForCompany $companyRecipients) {}

    /**
     * Tier A first: an email published in this posting (never another posting's), not
     * rejected, by id, skipping any address that already reached today's cap across all
     * clients (talent.outreach.tier_a_daily_cap). When the company is verified, tier B
     * (first smtp_verified named contact by id) and then tier C (SelectRecipientForCompany).
     * The cap is not locked across clients, so it may be exceeded by a few under concurrency.
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
            ->first();

        return $named ?? $this->companyRecipients->handle($company);
    }
}
