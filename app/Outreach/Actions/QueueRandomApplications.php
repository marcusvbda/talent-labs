<?php

namespace App\Outreach\Actions;

use App\Client\MatchScore;
use App\Enums\ApplicationOrigin;
use App\Models\User;
use App\Outreach\Data\PreferenceCriteria;
use App\Outreach\OutreachLimits;
use App\Outreach\Queries\MatchingJobPostings;
use App\Outreach\Support\QueueRejectionMessage;

class QueueRandomApplications
{
    /**
     * Candidates fetched per remaining slot, so skipped postings (no recipient, no profile...) still leave enough to fill the quota.
     */
    private const int CANDIDATE_FACTOR = 3;

    public function __construct(
        private CanSendApplications $eligibility,
        private QueueApplication $queueApplication,
    ) {}

    /**
     * Queue matching postings that can still get a recipient today (fresh within the posting age window),
     * best recipient tier first, then best match, random only as the final tie-break, until today's
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

        $criteria = $user->jobPreference !== null
            ? PreferenceCriteria::fromPreference($user->jobPreference)
            : PreferenceCriteria::empty();

        // Better recipients first, then best match; random only breaks ties so clients don't all get the same postings.
        $candidates = MatchScore::addSelects(
            MatchingJobPostings::sendableToday(MatchingJobPostings::withRecipientTierRank(MatchingJobPostings::forUser($user))),
            $criteria,
        )
            ->where('job_postings.first_seen_at', '>=', now()->subDays(OutreachLimits::MAX_POSTING_AGE_DAYS))
            ->orderBy('recipient_tier_rank')
            ->orderByDesc('match_score')
            ->inRandomOrder()
            ->limit($remaining * self::CANDIDATE_FACTOR)
            ->get();

        foreach ($candidates as $posting) {
            if (count($queued) >= $remaining) {
                break;
            }

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
