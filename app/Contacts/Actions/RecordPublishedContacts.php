<?php

namespace App\Contacts\Actions;

use App\Enums\ContactConfidence;
use App\Enums\ContactSource;
use App\Models\Company;
use App\Models\Contact;
use App\Models\JobPosting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class RecordPublishedContacts
{
    /**
     * Store the verdicts of the emails published in a posting. A sendable verdict with a posting is
     * that posting's contact; a rejected verdict, or one without a posting, is the company-level
     * verdict row (job_posting_id null) that stops the email from being probed again within the TTL.
     *
     * @param  array<string, ContactConfidence>  $verdicts
     */
    public function handle(Company $company, ?JobPosting $posting, array $verdicts): void
    {
        if ($verdicts === []) {
            return;
        }

        $freshSince = now()->subDays(VerifyPublishedEmails::VERDICT_TTL_DAYS);

        $stored = $company->contacts()
            ->where('source', ContactSource::Posting)
            ->whereIn('email', array_keys($verdicts))
            ->where('checked_at', '>=', $freshSince)
            ->orderByDesc('checked_at')
            ->get(['email', 'confidence', 'checked_at']);

        foreach ($verdicts as $email => $confidence) {
            $email = (string) $email;

            $key = $posting !== null && $confidence !== ContactConfidence::Rejected
                ? ['source' => ContactSource::Posting, 'job_posting_id' => $posting->id, 'email' => $email]
                : ['source' => ContactSource::Posting, 'job_posting_id' => null, 'email' => $email];

            /** @var Contact $contact */
            $contact = $company->contacts()->firstOrNew($key);

            if ($contact->exists
                && $contact->confidence === $confidence
                && $contact->checked_at->greaterThanOrEqualTo($freshSince)) {
                // Reused verdict: the row already says this, keep its date.
                continue;
            }

            // A verdict reused from another stored row keeps that row's date; a probed one is dated now.
            /** @var CarbonImmutable|null $reusedAt */
            $reusedAt = $stored->first(
                fn (Contact $row): bool => $row->email === $email && $row->confidence === $confidence,
            )?->checked_at;

            $contact->fill([
                'local_part' => Str::before($email, '@'),
                'confidence' => $confidence,
                'checked_at' => $reusedAt ?? now(),
            ])->save();
        }
    }
}
