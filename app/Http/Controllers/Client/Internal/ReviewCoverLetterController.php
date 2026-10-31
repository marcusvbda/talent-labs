<?php

namespace App\Http\Controllers\Client\Internal;

use App\Ai\Agents\GenerateCoverLetter;
use App\Ai\Jobs\GenerateCoverLetterJob;
use App\Ai\Support\CoverLetterEligibility;
use App\Ai\Support\CoverLetterFingerprint;
use App\Ai\Support\CoverLetterQuota;
use App\Client\ReviewDraftPresenter;
use App\Enums\AiUsageStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\GenerateCoverLetterRequest;
use App\Models\AiUsageRecord;
use App\Models\JobPosting;
use App\Models\User;
use App\Outreach\Queries\MatchingJobPostings;
use Illuminate\Http\JsonResponse;

class ReviewCoverLetterController extends Controller
{
    /**
     * Ask for the AI cover letter of one draft. Returns the `CoverLetterResult` contract:
     * `ready` with the body when the letter is already cached, `quota` or `off` with no body,
     * otherwise `writing` while `GenerateCoverLetterJob` runs (its outcome arrives as `cover_letter.updated`).
     */
    public function __invoke(GenerateCoverLetterRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $jobId = (int) $request->validated('jobId');
        $regenerate = $request->boolean('regenerate');

        $posting = MatchingJobPostings::forUser($user)
            ->where('job_postings.id', $jobId)
            ->with(['company', 'profile'])
            ->first();

        $profile = $posting instanceof JobPosting
            ? $user->applicationProfiles()
                ->where('language', $posting->profile?->language)
                ->where('is_active', true)
                ->first()
            : null;

        if (! $posting instanceof JobPosting || $profile === null || ! $profile->isComplete() || ! CoverLetterEligibility::applies($user, $profile)) {
            return response()->json(['state' => 'off', 'body' => null]);
        }

        if (! $regenerate) {
            $cached = CoverLetterFingerprint::cached($user, $posting, $profile);

            if ($cached !== null) {
                AiUsageRecord::query()->create([
                    'agent' => GenerateCoverLetter::CACHE_KEY,
                    'model' => (string) config('talent.ai.cover_letter.model'),
                    'job_posting_id' => $posting->id,
                    'user_id' => $user->id,
                    'cache_hit' => true,
                    'status' => AiUsageStatus::Completed,
                ]);

                return response()->json([
                    'state' => 'ready',
                    'body' => ReviewDraftPresenter::bodyFor($user, $posting, $profile, $cached),
                ]);
            }
        }

        if (CoverLetterQuota::exhausted($user)) {
            return response()->json(['state' => 'quota', 'body' => null]);
        }

        GenerateCoverLetterJob::dispatch($user->id, $posting->id, $regenerate);

        return response()->json(['state' => 'writing', 'body' => null]);
    }
}
