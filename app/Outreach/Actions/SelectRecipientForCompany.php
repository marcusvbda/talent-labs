<?php

namespace App\Outreach\Actions;

use App\Enums\ContactConfidence;
use App\Models\Company;
use App\Models\Contact;
use App\Outreach\OutreachLimits;

class SelectRecipientForCompany
{
    /**
     * The company's smtp_verified contact with the highest RECIPIENT_PRIORITY
     * (lowest index). Aliases outside that list are never used.
     */
    public function handle(Company $company): ?Contact
    {
        $priority = OutreachLimits::RECIPIENT_PRIORITY;

        return $company->contacts()
            ->where('confidence', ContactConfidence::SmtpVerified)
            ->whereIn('local_part', $priority)
            ->get()
            ->sortBy(fn (Contact $contact): int => (int) array_search($contact->local_part, $priority, true))
            ->first();
    }
}
