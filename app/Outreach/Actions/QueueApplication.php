<?php

namespace App\Outreach\Actions;

use App\Enums\ApplicationOrigin;
use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\JobPosting;
use App\Models\JobPreference;
use App\Models\User;
use App\Outreach\Jobs\SendApplicationEmail;
use App\Outreach\Queries\MatchingJobPostings;
use App\Outreach\Support\ApplicationTemplateRenderer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class QueueApplication
{
    public const string REJECT_NO_MATCH = 'This job no longer matches your preferences.';

    public const string REJECT_NO_RECIPIENT = 'No verified recipient for this company.';

    public const string REJECT_ALREADY_APPLIED = 'You already applied to this company.';

    /**
     * Prefix of the eligibility rejection: "Not eligible: " followed by the
     * unmet items of CanSendApplications joined with a single space, e.g.
     * "Not eligible: Upload your CV. Daily limit of 10 applications reached."
     */
    public const string REJECT_NOT_ELIGIBLE_PREFIX = 'Not eligible: ';

    private ?string $rejectionReason = null;

    public function __construct(
        private CanSendApplications $eligibility,
        private SelectRecipientForCompany $recipients,
    ) {}

    public function handle(User $user, JobPosting $posting, ApplicationOrigin $origin): ?Application
    {
        $this->rejectionReason = null;

        try {
            return DB::transaction(fn (): ?Application => $this->queue($user, $posting, $origin));
        } catch (UniqueConstraintViolationException) {
            return $this->reject(self::REJECT_ALREADY_APPLIED);
        }
    }

    /**
     * Reason of the last handle() call; null when it queued an application.
     */
    public function rejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    private function queue(User $user, JobPosting $posting, ApplicationOrigin $origin): ?Application
    {
        // Serializes concurrent queueing for the same user.
        $preference = JobPreference::query()->whereBelongsTo($user)->lockForUpdate()->first();

        $eligibility = $this->eligibility->check($user);

        if (! $eligibility->ok()) {
            return $this->reject(self::REJECT_NOT_ELIGIBLE_PREFIX.implode(' ', $eligibility->unmet));
        }

        if ($preference === null) {
            return $this->reject(self::REJECT_NOT_ELIGIBLE_PREFIX.'Upload your CV.');
        }

        $user->setRelation('jobPreference', $preference);

        $company = $posting->company;

        // Checked before the match: an already-contacted company no longer belongs to the pool.
        if ($company !== null && Application::query()->whereBelongsTo($user)->where('company_id', $company->id)->exists()) {
            return $this->reject(self::REJECT_ALREADY_APPLIED);
        }

        $contact = $company === null ? null : $this->recipients->handle($company);

        if ($company === null || $contact === null) {
            return $this->reject(self::REJECT_NO_RECIPIENT);
        }

        if (! MatchingJobPostings::forUser($user)->where('job_postings.id', $posting->id)->exists()) {
            return $this->reject(self::REJECT_NO_MATCH);
        }

        $variables = ApplicationTemplateRenderer::variablesFor($user, $posting);

        $application = Application::query()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'job_posting_id' => $posting->id,
            'contact_id' => $contact->id,
            'recipient_email' => $contact->email,
            'subject' => ApplicationTemplateRenderer::render((string) $preference->email_subject, $variables),
            'body' => ApplicationTemplateRenderer::render((string) $preference->email_body, $variables),
            'origin' => $origin,
            'status' => ApplicationStatus::Queued,
            'attempts' => 0,
            'queued_at' => now(),
            'scheduled_for' => now(),
        ]);

        // Sent right away: no spacing between a client's sends (owner decision).
        SendApplicationEmail::dispatch($application->id)->afterCommit();

        return $application;
    }

    private function reject(string $reason): null
    {
        $this->rejectionReason = $reason;

        return null;
    }
}
