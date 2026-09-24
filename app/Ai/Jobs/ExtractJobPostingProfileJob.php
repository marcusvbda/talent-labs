<?php

namespace App\Ai\Jobs;

use App\Ai\Agents\ExtractJobPostingProfile;
use App\Enums\AiUsageStatus;
use App\Enums\OutreachStatus;
use App\Enums\ProfileStatus;
use App\Models\AiAgentResponseCache;
use App\Models\AiUsageRecord;
use App\Models\JobPosting;
use App\Models\JobPostingProfile;
use App\Outreach\Support\StackNormalizer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;
use UnexpectedValueException;

class ExtractJobPostingProfileJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * Must stay below the database queue's retry_after (90).
     */
    public int $timeout = 75;

    public int $uniqueFor = 3600;

    public function __construct(public int $jobPostingId)
    {
        $this->onQueue('ai');
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 90];
    }

    public function uniqueId(): string
    {
        return (string) $this->jobPostingId;
    }

    public function handle(): void
    {
        $posting = JobPosting::query()->with(['company', 'profile'])->find($this->jobPostingId);

        if ($posting === null || $posting->company?->outreach_status !== OutreachStatus::Verified) {
            return;
        }

        if (
            $posting->profile?->status === ProfileStatus::Done
            && $posting->profile->schema_version === ExtractJobPostingProfile::CACHE_SCHEMA_VERSION
        ) {
            return;
        }

        $agent = new ExtractJobPostingProfile($posting);
        $context = $agent->postingContext();
        $fingerprint = ExtractJobPostingProfile::CACHE_SCHEMA_VERSION."\n---\n".$agent->instructions()."\n---\n".$context;
        $startedAt = hrtime(true);

        $cached = AiAgentResponseCache::lookup(ExtractJobPostingProfile::CACHE_KEY, ExtractJobPostingProfile::MODEL, $fingerprint);

        if ($cached !== null) {
            $this->persist($posting, $cached);
            $this->recordUsage(AiUsageStatus::Completed, true, null, $startedAt);

            return;
        }

        $usage = null;

        try {
            $response = $agent->prompt($context);

            if (! $response instanceof StructuredAgentResponse) {
                throw new UnexpectedValueException('The posting profile agent did not return structured output.');
            }

            $usage = $response->usage;
            $structured = $response->toArray();

            $this->persist($posting, $structured);
            AiAgentResponseCache::remember(ExtractJobPostingProfile::CACHE_KEY, ExtractJobPostingProfile::MODEL, $fingerprint, $structured);
            $this->recordUsage(AiUsageStatus::Completed, false, $usage, $startedAt);
        } catch (Throwable $exception) {
            $this->recordUsage(AiUsageStatus::Failed, false, $usage, $startedAt);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        if (! JobPosting::query()->whereKey($this->jobPostingId)->exists()) {
            return;
        }

        JobPostingProfile::query()->updateOrCreate(
            ['job_posting_id' => $this->jobPostingId],
            [
                'status' => ProfileStatus::Failed,
                'schema_version' => ExtractJobPostingProfile::CACHE_SCHEMA_VERSION,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persist(JobPosting $posting, array $data): void
    {
        $seniority = $data['seniority'] ?? null;
        $summary = $this->nullableString($data['summary'] ?? null);

        JobPostingProfile::query()->updateOrCreate(
            ['job_posting_id' => $posting->id],
            [
                'status' => ProfileStatus::Done,
                'schema_version' => ExtractJobPostingProfile::CACHE_SCHEMA_VERSION,
                'normalized_title' => $this->nullableString($data['normalized_title'] ?? null),
                'seniority' => in_array($seniority, ExtractJobPostingProfile::SENIORITIES, true) ? $seniority : 'unknown',
                'stack' => StackNormalizer::normalize(is_array($data['stack'] ?? null) ? $data['stack'] : []),
                'locations' => $this->stringList($data['locations'] ?? null),
                'is_remote' => is_bool($data['is_remote'] ?? null) ? $data['is_remote'] : null,
                'summary' => $summary === null ? null : mb_substr($summary, 0, 300),
                'extracted_at' => now(),
            ],
        );
    }

    private function recordUsage(AiUsageStatus $status, bool $cacheHit, ?Usage $usage, int $startedAt): void
    {
        AiUsageRecord::query()->create([
            'agent' => ExtractJobPostingProfile::CACHE_KEY,
            'model' => ExtractJobPostingProfile::MODEL,
            'job_posting_id' => $this->jobPostingId,
            'input_tokens' => $usage->promptTokens ?? 0,
            'output_tokens' => $usage->completionTokens ?? 0,
            'cache_hit' => $cacheHit,
            'duration_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
            'status' => $status,
        ]);
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn (mixed $item): ?string => $this->nullableString($item), $value),
            fn (?string $item): bool => $item !== null,
        ));
    }
}
