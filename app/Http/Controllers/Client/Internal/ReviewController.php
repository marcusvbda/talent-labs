<?php

namespace App\Http\Controllers\Client\Internal;

use App\Client\AccountStatusPresenter;
use App\Client\ReviewDraftPresenter;
use App\Enums\ApplicationOrigin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\QueueReviewedBatchRequest;
use App\Http\Requests\Client\QueueReviewedRequest;
use App\Http\Requests\Client\ReviewDraftsRequest;
use App\Models\JobPosting;
use App\Models\User;
use App\Outreach\Actions\QueueApplication;
use App\Outreach\Queries\MatchingJobPostings;
use App\Outreach\Support\QueueRejectionMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

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

        $this->queueReviewed(
            $queueApplication,
            $user,
            $jobId,
            $posting,
            (string) $request->validated('subject'),
            (string) $request->validated('body'),
            $queued,
            $rejected,
        );

        return response()->json([
            'queued' => $queued,
            'rejected' => $rejected,
            'quota' => $presenter->quota($user),
        ]);
    }

    /**
     * Queue several reviewed emails in the given order (Pro plan). Returns the `QueueResult` contract.
     */
    public function reviewedBatch(QueueReviewedBatchRequest $request, QueueApplication $queueApplication, AccountStatusPresenter $presenter): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var list<array{jobId: int|string, subject: ?string, body: ?string}> $drafts */
        $drafts = $request->validated('drafts');

        $postings = JobPosting::query()
            ->whereKey(array_map(fn (array $draft): int => (int) $draft['jobId'], $drafts))
            ->get()
            ->keyBy('id');

        $queued = [];
        $rejected = [];

        foreach ($drafts as $draft) {
            $jobId = (int) $draft['jobId'];
            $subject = $draft['subject'];
            $body = $draft['body'];

            $validator = Validator::make(
                ['subject' => $subject, 'body' => $body],
                ['subject' => QueueReviewedRequest::subjectRules(), 'body' => QueueReviewedRequest::bodyRules()],
                QueueReviewedRequest::contentMessages(),
            );

            if ($validator->fails()) {
                $rejected[] = ['jobId' => $jobId, 'reason' => (string) $validator->errors()->first()];

                continue;
            }

            $this->queueReviewed(
                $queueApplication,
                $user,
                $jobId,
                $postings->get($jobId),
                (string) $subject,
                (string) $body,
                $queued,
                $rejected,
            );
        }

        return response()->json([
            'queued' => $queued,
            'rejected' => $rejected,
            'quota' => $presenter->quota($user),
        ]);
    }

    /**
     * Queue one reviewed email, appending to the `QueueResult` queued/rejected lists.
     *
     * @param  list<array{jobId: int, applicationId: int, scheduledFor: ?string}>  $queued
     * @param  list<array{jobId: int, reason: string}>  $rejected
     */
    private function queueReviewed(
        QueueApplication $queueApplication,
        User $user,
        int $jobId,
        ?JobPosting $posting,
        string $subject,
        string $body,
        array &$queued,
        array &$rejected,
    ): void {
        if (! $posting instanceof JobPosting) {
            $rejected[] = ['jobId' => $jobId, 'reason' => QueueRejectionMessage::for('missing')];

            return;
        }

        $application = $queueApplication->handle(
            $user,
            $posting,
            ApplicationOrigin::Manual,
            subject: $subject,
            body: $body,
        );

        if ($application === null) {
            $rejected[] = ['jobId' => $jobId, 'reason' => QueueRejectionMessage::for((string) $queueApplication->rejectionReason())];

            return;
        }

        $queued[] = [
            'jobId' => $jobId,
            'applicationId' => $application->id,
            'scheduledFor' => $application->scheduled_for?->toIso8601String(),
        ];
    }
}
