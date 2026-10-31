<?php

namespace App\Ai\Jobs;

use App\Ai\Agents\GenerateCoverLetter;
use App\Ai\Support\CoverLetterEligibility;
use App\Ai\Support\CoverLetterFingerprint;
use App\Ai\Support\CoverLetterQuota;
use App\Ai\Support\CoverLetterValidator;
use App\Client\ReviewDraftPresenter;
use App\Enums\AiUsageStatus;
use App\Events\Client\Concerns\DispatchesClientEvent;
use App\Events\Client\CoverLetterUpdated;
use App\Models\AiAgentResponseCache;
use App\Models\AiUsageRecord;
use App\Models\Application;
use App\Models\ApplicationProfile;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

/**
 * Generates the cover letter of one posting for the review screen and tells the client
 * through `cover_letter.updated`. Never retried: a failed generation falls back to the
 * client's manual letter. Exception messages of AI calls are never logged or reported.
 */
class GenerateCoverLetterJob implements ShouldBeUnique, ShouldQueue
{
    use DispatchesClientEvent;
    use Queueable;

    private const ATTEMPTS = 2;

    private const AGENT_TIMEOUT = 30;

    public int $tries = 1;

    /**
     * Must stay below the database queue's retry_after (90).
     */
    public int $timeout = 75;

    public int $uniqueFor = 90;

    public function __construct(public int $userId, public int $jobPostingId, public bool $regenerate = false)
    {
        $this->onQueue('ai');
    }

    public function uniqueId(): string
    {
        return "{$this->userId}:{$this->jobPostingId}";
    }

    public function handle(): void
    {
        $user = User::query()->find($this->userId);

        if ($user === null) {
            return;
        }

        $posting = JobPosting::query()->with(['company', 'profile'])->find($this->jobPostingId);

        // A posting already applied to has nothing left to review.
        if ($posting !== null && Application::query()->whereBelongsTo($user)->where('job_posting_id', $posting->id)->exists()) {
            return;
        }

        $profile = $posting === null ? null : $user->applicationProfiles()
            ->where('language', $posting->profile?->language)
            ->where('is_active', true)
            ->first();

        if ($posting === null || $profile === null || ! $profile->isComplete() || ! CoverLetterEligibility::applies($user, $profile)) {
            $this->sendManual($user, CoverLetterUpdated::REASON_FAILED);

            return;
        }

        $model = (string) config('talent.ai.cover_letter.model');

        if (! $this->regenerate) {
            $startedAt = hrtime(true);
            $cached = CoverLetterFingerprint::cached($user, $posting, $profile);

            if ($cached !== null) {
                $this->recordUsage($model, AiUsageStatus::Completed, true, null, $startedAt);
                $this->sendReady($user, $posting, $profile, $cached);

                return;
            }
        }

        if (CoverLetterQuota::exhausted($user)) {
            $this->sendManual($user, CoverLetterUpdated::REASON_QUOTA);

            return;
        }

        $language = $profile->language->value;
        $agent = new GenerateCoverLetter($user, $posting, $profile);
        $context = null;

        for ($attempt = 1; $attempt <= self::ATTEMPTS; $attempt++) {
            $startedAt = hrtime(true);
            $usage = null;
            $letter = null;

            try {
                $context ??= $agent->context();

                $response = $agent->prompt(
                    $context,
                    provider: (string) config('talent.ai.cover_letter.provider'),
                    model: $model,
                    timeout: self::AGENT_TIMEOUT,
                );

                $usage = $response->usage;

                if ($response instanceof StructuredAgentResponse) {
                    $letter = CoverLetterValidator::accepts($response->toArray(), $language);
                }
            } catch (Throwable) {
                // A provider failure is a failed attempt; its message may carry prompt text.
                $letter = null;
            }

            $this->recordUsage($model, $letter === null ? AiUsageStatus::Failed : AiUsageStatus::Completed, false, $usage, $startedAt);

            if ($letter !== null) {
                AiAgentResponseCache::overwrite(
                    GenerateCoverLetter::CACHE_KEY,
                    $model,
                    CoverLetterFingerprint::for($user, $posting, $profile),
                    ['cover_letter' => $letter, 'language' => $language],
                    $user->id,
                );

                $this->sendReady($user, $posting, $profile, $letter);

                return;
            }
        }

        $this->sendManual($user, CoverLetterUpdated::REASON_FAILED);
    }

    public function failed(?Throwable $exception): void
    {
        $user = User::query()->find($this->userId);

        if ($user !== null) {
            $this->sendManual($user, CoverLetterUpdated::REASON_FAILED);
        }
    }

    private function sendReady(User $user, JobPosting $posting, ApplicationProfile $profile, string $letter): void
    {
        self::dispatchClientEvent(new CoverLetterUpdated(
            $user->id,
            $this->jobPostingId,
            CoverLetterUpdated::STATE_READY,
            null,
            ReviewDraftPresenter::bodyFor($user, $posting, $profile, $letter),
        ));
    }

    /**
     * @param  CoverLetterUpdated::REASON_*  $reason
     */
    private function sendManual(User $user, string $reason): void
    {
        self::dispatchClientEvent(new CoverLetterUpdated($user->id, $this->jobPostingId, CoverLetterUpdated::STATE_MANUAL, $reason));
    }

    private function recordUsage(string $model, AiUsageStatus $status, bool $cacheHit, ?Usage $usage, int $startedAt): void
    {
        AiUsageRecord::query()->create([
            'agent' => GenerateCoverLetter::CACHE_KEY,
            'model' => $model,
            'job_posting_id' => $this->jobPostingId,
            'user_id' => $this->userId,
            'input_tokens' => $usage->promptTokens ?? 0,
            'output_tokens' => $usage->completionTokens ?? 0,
            'cache_hit' => $cacheHit,
            'duration_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
            'status' => $status,
        ]);
    }
}
