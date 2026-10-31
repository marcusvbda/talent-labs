<?php

namespace App\Jobs;

use App\Actions\Collection\UpsertJobPostings;
use App\Ai\Jobs\ExtractJobPostingProfileJob;
use App\Collection\Data\JobPostingData;
use App\Collection\Data\SourceContactData;
use App\Collection\Support\PostingRestrictions;
use App\Collection\Support\PostingTitle;
use App\Collection\Support\PublishedEmailExtractor;
use App\Collection\Support\RoleClassifier;
use App\Contacts\Actions\ResolveCompanyForPosting;
use App\Contacts\Actions\RunCompanyDiscovery;
use App\Enums\SourceContactKind;
use App\Enums\SourceRunStatus;
use App\Models\JobPosting;
use App\Models\SourceRun;
use Carbon\CarbonInterface;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Marcusvbda\FilamentRealtimeDriver\RealtimeEvent;
use Throwable;

class FetchJobsFromSource implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    private const CHUNK_SIZE = 200;

    /**
     * Length of the varchar columns (`title`, `company_name`, `location`, `department`,
     * `employment_type`): one oversized value would make the whole upsert chunk fail.
     */
    private const MAX_STRING = 255;

    public function __construct(public int $sourceRunId) {}

    public function handle(): void
    {
        $sourceRun = SourceRun::with('source')->find($this->sourceRunId);

        if ($sourceRun === null || $this->batch()?->cancelled()) {
            return;
        }

        $sourceRun->status = SourceRunStatus::Running;
        $sourceRun->started_at = now();
        $sourceRun->save();

        $source = $sourceRun->source;

        try {
            // Keyed by external id so the last occurrence wins: one ON CONFLICT
            // statement can't touch the same row twice.
            $items = [];

            foreach ($source->adapter->adapter()->fetch($source) as $item) {
                $items[$item->externalId] = $item;
            }

            $classifier = app(RoleClassifier::class);
            $upsert = app(UpsertJobPostings::class);
            $targetFamilies = config('talent.collection.target_role_families');

            /** @var array<string, list<array{item: JobPostingData, row: array<string, mixed>}>> $newByCompany */
            $newByCompany = [];

            foreach (array_chunk($items, self::CHUNK_SIZE, true) as $chunk) {
                $existing = JobPosting::query()
                    ->where('source_id', $source->id)
                    ->whereIn('external_id', array_map('strval', array_keys($chunk)))
                    ->pluck('title', 'external_id')
                    ->all();

                $now = now();
                $existingRows = [];

                foreach ($chunk as $item) {
                    $row = $this->row($item, $existing, $sourceRun, $classifier, $now);

                    if (array_key_exists($item->externalId, $existing)) {
                        $existingRows[] = $row;

                        continue;
                    }

                    // New postings outside the target role families are never stored.
                    if ($row['role_family'] === null || ! in_array($row['role_family'], $targetFamilies, true)) {
                        continue;
                    }

                    // Grouped by the (truncated) stored name, the one the company is resolved from.
                    $newByCompany[ResolveCompanyForPosting::normalize($row['company_name'])][] = ['item' => $item, 'row' => $row];
                }

                // Known postings are refreshed as before, whatever their company's state.
                $upsert->handle($existingRows);
            }

            $jobsNew = 0;
            $storedExternalIds = [];
            $deferred = [];
            $resolveCompany = app(ResolveCompanyForPosting::class);
            $discovery = app(RunCompanyDiscovery::class);

            // A new posting is stored only once its company is verified for sending.
            foreach ($newByCompany as $group) {
                $company = $resolveCompany->forName($group[0]['row']['company_name']);
                $rows = array_column($group, 'row');

                if ($company->isVerifiedForSending()) {
                    $rows = array_map(fn (array $row): array => [...$row, 'company_id' => $company->id], $rows);
                    $upsert->handle($rows);

                    $jobsNew += count($rows);
                    array_push($storedExternalIds, ...array_column($rows, 'external_id'));

                    continue;
                }

                if ($company->isClosedForSending() || $discovery->isCoolingDown($company)) {
                    continue;
                }

                $websites = array_values(array_unique(array_filter(
                    array_map(fn (array $entry): ?string => $entry['item']->companyWebsite, $group),
                    fn (?string $website): bool => $website !== null && $website !== '',
                )));

                $deferred[] = new StoreCompanyPostings($sourceRun->id, $company->id, $rows, $websites);
            }

            $sourceRun->status = SourceRunStatus::Completed;
            $sourceRun->jobs_fetched = count($items);
            $sourceRun->jobs_new = $jobsNew;
            $sourceRun->finished_at = now();
            $sourceRun->save();

            $source->last_run_at = now();
            $source->last_run_status = SourceRunStatus::Completed;
            $source->save();
        } catch (Throwable $e) {
            $this->markFailed($sourceRun, $e);

            return;
        }

        // The upsert skips model events: announce the new/updated postings once.
        try {
            RealtimeEvent::dispatch('job_postings', 'JobPostingsUpdated', ['source_run_id' => $sourceRun->id]);
        } catch (BroadcastException $e) {
            report($e);
        }

        try {
            foreach (array_chunk($storedExternalIds, self::CHUNK_SIZE) as $chunk) {
                JobPosting::query()
                    ->where('source_id', $source->id)
                    ->whereIn('external_id', array_map('strval', $chunk))
                    ->pluck('id')
                    ->each(fn (int $id) => ExtractJobPostingProfileJob::dispatch($id));
            }
        } catch (Throwable $e) {
            report($e);
        }

        // Queued only after the source run is saved, so their jobs_new increments are never overwritten.
        // Added to the run's batch, its finally callback waits for them.
        try {
            foreach ($deferred as $job) {
                $batch = $this->batch();

                if ($batch !== null) {
                    $batch->add([$job]);
                } else {
                    dispatch($job)->onQueue('collection');
                }
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * The posting in column form, shared by the refresh of known postings and the storage of new ones.
     *
     * @param  array<string, string>  $existing  title by external id
     * @return array<string, mixed>
     */
    private function row(JobPostingData $item, array $existing, SourceRun $sourceRun, RoleClassifier $classifier, CarbonInterface $now): array
    {
        return [
            'source_id' => $sourceRun->source_id,
            'collection_run_id' => $sourceRun->collection_run_id,
            'last_seen_run_id' => $sourceRun->collection_run_id,
            'external_id' => $item->externalId,
            'title' => mb_substr($this->title($item, $existing), 0, self::MAX_STRING),
            'company_name' => mb_substr($item->companyName, 0, self::MAX_STRING),
            'location' => $this->fit($item->location),
            'is_remote' => $item->isRemote,
            'department' => $this->fit($item->department),
            'employment_type' => $this->fit($item->employmentType),
            'role_family' => $classifier->classify($item->title, $this->tags($item))?->value,
            'ineligible_reason' => $this->fit($item->ineligibleReason),
            'restrictions' => json_encode(PostingRestrictions::detect($item->title, $item->location, $item->descriptionText)),
            'url' => $item->url,
            'apply_url' => $item->applyUrl,
            'company_website' => $item->companyWebsite,
            'source_contacts' => $this->sourceContacts($item),
            'description_html' => $item->descriptionHtml,
            'description_text' => $item->descriptionText,
            'published_at' => $item->publishedAt?->setTimezone(config('app.timezone')),
            // Query-builder upserts skip Eloquent casts.
            'raw' => json_encode($item->raw),
            'first_seen_at' => $now,
            'last_seen_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * The adapter's contacts plus the emails the posting publishes that the adapter didn't return,
     * JSON-encoded; null when there are none, so the upsert keeps the stored contacts.
     */
    private function sourceContacts(JobPostingData $item): ?string
    {
        $contacts = $item->sourceContacts;
        $known = [];

        foreach ($contacts as $contact) {
            if ($contact->email !== null) {
                $known[] = mb_strtolower($contact->email);
            }
        }

        foreach (PublishedEmailExtractor::extract($item->descriptionHtml, $item->descriptionText, $item->applyUrl) as $email) {
            if (! in_array($email, $known, true)) {
                $contacts[] = new SourceContactData(SourceContactKind::Email, email: $email);
                $known[] = $email;
            }
        }

        return $contacts === []
            ? null
            : json_encode(array_map(fn (SourceContactData $c): array => $c->toArray(), $contacts), JSON_THROW_ON_ERROR);
    }

    /**
     * Covers timeouts and killed workers, where handle() never reached its catch.
     */
    public function failed(Throwable $e): void
    {
        $sourceRun = SourceRun::with('source')->find($this->sourceRunId);

        if ($sourceRun === null || $sourceRun->finished_at !== null) {
            return;
        }

        $this->markFailed($sourceRun, $e);
    }

    /**
     * Source-provided labels (tags, industry, category) used as a classification fallback.
     *
     * @return list<string>
     */
    private function tags(JobPostingData $item): array
    {
        $tags = [];

        foreach (['tags', 'jobIndustry', 'category', 'category_name'] as $key) {
            $value = $item->raw[$key] ?? null;

            if (is_string($value)) {
                $value = $key === 'tags' ? explode(',', $value) : [$value];
            }

            if (! is_array($value)) {
                continue;
            }

            foreach ($value as $tag) {
                if (is_string($tag) && trim($tag) !== '') {
                    $tags[] = trim($tag);
                }
            }
        }

        return $tags;
    }

    /**
     * Postings are kept even when the feed gives no readable title: reuse the title we
     * already stored (possibly set by the AI extraction), else a placeholder that the
     * profile extraction replaces.
     *
     * @param  array<string, string>  $existing  title by external id
     */
    private function title(JobPostingData $item, array $existing): string
    {
        if (PostingTitle::isUsable($item->title)) {
            return trim($item->title);
        }

        $stored = $existing[$item->externalId] ?? null;

        return $stored !== null && PostingTitle::isUsable($stored)
            ? $stored
            : PostingTitle::placeholder($item->companyName);
    }

    private function fit(?string $value): ?string
    {
        return $value === null ? null : mb_substr($value, 0, self::MAX_STRING);
    }

    private function markFailed(SourceRun $sourceRun, Throwable $e): void
    {
        $sourceRun->status = SourceRunStatus::Failed;
        $sourceRun->error_message = Str::limit($e->getMessage(), 1000);
        $sourceRun->finished_at = now();
        $sourceRun->save();

        $sourceRun->source->last_run_at = now();
        $sourceRun->source->last_run_status = SourceRunStatus::Failed;
        $sourceRun->source->save();

        Log::warning('FetchJobsFromSource failed', [
            'source_run_id' => $sourceRun->id,
            'exception' => $e,
        ]);
    }
}
