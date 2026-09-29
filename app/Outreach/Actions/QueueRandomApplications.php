<?php

namespace App\Outreach\Actions;

use App\Enums\ApplicationOrigin;
use App\Models\User;
use App\Outreach\OutreachLimits;
use App\Outreach\Queries\MatchingJobPostings;
use App\Outreach\Support\QueueRejectionMessage;

class QueueRandomApplications
{
    /**
     * Candidates fetched per remaining slot, so skipped postings (same company, no recipient...) still leave enough to fill the quota.
     */
    private const int CANDIDATE_FACTOR = 3;

    public function __construct(
        private CanSendApplications $eligibility,
        private QueueApplication $queueApplication,
    ) {}

    /**
     * Queue randomly chosen matching postings (fresh within the posting age window) until today's
     * remaining quota is used or the candidates run out. Random sends are recorded as manual and use
     * the profile templates for every plan. Each handle() commits its own transaction, so send slots
     * chain in order and broadcasts fire exactly as for hand-picked postings.
     *
     * @return array{queued: list<array{jobId: int, applicationId: int, scheduledFor: string|null}>, rejected: list<array{jobId: int, reason: string}>}
     */
    public function handle(User $user): array
    {
        $queued = [];
        $rejected = [];

        $remaining = $this->eligibility->check($user)->remaining;

        if ($remaining <= 0) {
            return ['queued' => $queued, 'rejected' => $rejected];
        }

        $candidates = MatchingJobPostings::forUser($user)
            ->where('job_postings.first_seen_at', '>=', now()->subDays(OutreachLimits::MAX_POSTING_AGE_DAYS))
            ->inRandomOrder()
            ->limit($remaining * self::CANDIDATE_FACTOR)
            ->get();

        /** @var array<int, true> $attemptedCompanies */
        $attemptedCompanies = [];

        foreach ($candidates as $posting) {
            if (count($queued) >= $remaining) {
                break;
            }

            if (isset($attemptedCompanies[$posting->company_id])) {
                continue;
            }

            $attemptedCompanies[$posting->company_id] = true;

            $application = $this->queueApplication->handle($user, $posting, ApplicationOrigin::Manual);

            if ($application === null) {
                $reason = (string) $this->queueApplication->rejectionReason();
                $rejected[] = ['jobId' => $posting->id, 'reason' => QueueRejectionMessage::for($reason)];

                // Quota used up or account state changed: no later candidate can be queued either.
                if (str_starts_with($reason, QueueApplication::REJECT_NOT_ELIGIBLE_PREFIX)) {
                    break;
                }

                continue;
            }

            $queued[] = [
                'jobId' => $posting->id,
                'applicationId' => $application->id,
                'scheduledFor' => $application->scheduled_for?->toIso8601String(),
            ];
        }

        return ['queued' => $queued, 'rejected' => $rejected];
    }
}
