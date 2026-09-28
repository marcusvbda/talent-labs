<?php

namespace App\Http\Controllers\Client\Internal;

use App\Client\AccountStatusPresenter;
use App\Client\ReviewDraftPresenter;
use App\Enums\ApplicationOrigin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\QueueReviewedRequest;
use App\Http\Requests\Client\ReviewDraftsRequest;
use App\Models\JobPosting;
use App\Models\User;
use App\Outreach\Actions\QueueApplication;
use App\Outreach\Queries\MatchingJobPostings;
use App\Outreach\Support\QueueRejectionMessage;
use Illuminate\Http\JsonResponse;

class ReviewController extends Controller
{
    /**
     * `ReviewDraft[]` in request order (Pro plan). Postings outside the pool or without an
     * active, complete profile in their language are omitted.
     */
    public function drafts(ReviewDraftsRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var list<int> $jobIds */
        $jobIds = array_map(intval(...), $request->validated('jobIds'));

        $drafts = [];

        foreach ($jobIds as $jobId) {
            $posting = MatchingJobPostings::forUser($user)
                ->where('job_postings.id', $jobId)
                ->with(['company', 'profile'])
                ->first();

            if (! $posting instanceof JobPosting) {
                continue;
            }

            $profile = $user->applicationProfiles()
                ->where('language', $posting->profile?->language)
                ->where('is_active', true)
                ->first();

            if ($profile === null || ! $profile->isComplete()) {
                continue;
            }

            $drafts[] = ReviewDraftPresenter::forUser($user, $posting, $profile);
        }

        return response()->json($drafts);
    }

    /**
     * Queue one reviewed email as edited. Returns the `QueueResult` contract.
     */
    public function reviewed(QueueReviewedRequest $request, QueueApplication $queueApplication, AccountStatusPresenter $presenter): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $jobId = (int) $request->validated('jobId');

        $posting = JobPosting::query()->find($jobId);

        $queued = [];
        $rejected = [];

        if (! $posting instanceof JobPosting) {
            $rejected[] = ['jobId' => $jobId, 'reason' => QueueRejectionMessage::for('missing')];
        } else {
            $application = $queueApplication->handle(
                $user,
                $posting,
                ApplicationOrigin::Manual,
                subject: (string) $request->validated('subject'),
                body: (string) $request->validated('body'),
            );

            if ($application === null) {
                $rejected[] = ['jobId' => $jobId, 'reason' => QueueRejectionMessage::for((string) $queueApplication->rejectionReason())];
            } else {
                $queued[] = [
                    'jobId' => $jobId,
                    'applicationId' => $application->id,
                    'scheduledFor' => $application->scheduled_for?->toIso8601String(),
                ];
            }
        }

        return response()->json([
            'queued' => $queued,
            'rejected' => $rejected,
            'quota' => $presenter->quota($user),
        ]);
    }
}
