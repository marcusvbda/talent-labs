<?php

namespace App\Contacts\Jobs;

use App\Ai\Jobs\ExtractJobPostingProfileJob;
use App\Collection\Support\PublishedEmailExtractor;
use App\Contacts\Actions\RecordPublishedContacts;
use App\Contacts\Actions\VerifyPublishedEmails;
use App\Enums\ContactSource;
use App\Models\Contact;
use App\Models\JobPosting;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Marcusvbda\FilamentRealtimeDriver\RealtimeEvent;
use Throwable;

/**
 * Probes the emails a posting publishes and records them as the company's posting contacts.
 */
class SyncPublishedContacts implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    private const CHUNK_SIZE = 200;

    public int $tries = 1;

    /**
     * Must stay below the database queue's retry_after (90).
     */
    public int $timeout = 75;

    public int $uniqueFor = 3600;

    public function __construct(public int $jobPostingId)
    {
        $this->onQueue('contacts');
    }

    public function uniqueId(): string
    {
        return (string) $this->jobPostingId;
    }

    /**
     * Queue the job for the source's postings that publish emails, have a company, are eligible,
     * belong to a target role family and have no posting contact yet. Two queries per chunk.
     *
     * @param  list<string>  $externalIds
     */
    public static function dispatchForPostings(int $sourceId, array $externalIds): void
    {
        /** @var list<string> $targetFamilies */
        $targetFamilies = config('talent.collection.target_role_families');

        foreach (array_chunk(array_values(array_unique($externalIds)), self::CHUNK_SIZE) as $chunk) {
            $postings = JobPosting::query()
                ->where('source_id', $sourceId)
                ->whereIn('external_id', array_map('strval', $chunk))
                ->whereNotNull('company_id')
                ->whereNull('ineligible_reason')
                ->whereIn('role_family', $targetFamilies)
                ->whereNotNull('source_contacts')
                ->get(['id', 'source_contacts'])
                ->filter(fn (JobPosting $posting): bool => PublishedEmailExtractor::emailsOf($posting->source_contacts) !== []);

            if ($postings->isEmpty()) {
                continue;
            }

            $withContact = Contact::query()
                ->where('source', ContactSource::Posting)
                ->whereIn('job_posting_id', $postings->modelKeys())
                ->distinct()
                ->pluck('job_posting_id')
                ->all();

            $postings
                ->reject(fn (JobPosting $posting): bool => in_array($posting->id, $withContact, true))
                ->each(fn (JobPosting $posting) => self::dispatch($posting->id));
        }
    }

    public function handle(VerifyPublishedEmails $verify, RecordPublishedContacts $record): void
    {
        try {
            $posting = JobPosting::query()->with('company')->find($this->jobPostingId);

            if ($posting === null || $posting->ineligible_reason !== null) {
                return;
            }

            $company = $posting->company;

            if ($company === null) {
                return;
            }

            $emails = array_values(array_filter(
                PublishedEmailExtractor::emailsOf($posting->source_contacts),
                fn (string $email): bool => PublishedEmailExtractor::matchesCompany($email, $company),
            ));

            if ($emails === []) {
                return;
            }

            $verdicts = $verify->handle($company, $emails);

            if ($verdicts === []) {
                return;
            }

            $record->handle($company, $posting, $verdicts);

            if (
                $posting->hasSendableTierA()
                && JobPosting::query()->whereKey($posting->id)->needingProfile()->exists()
            ) {
                ExtractJobPostingProfileJob::dispatch($posting->id);
            }

            // Contacts surface on the company pages: announce the change once.
            try {
                RealtimeEvent::dispatch('companies', 'CompanyUpdated', ['id' => $company->id]);
            } catch (BroadcastException $e) {
                report($e);
            }
        } catch (Throwable $e) {
            Log::warning('SyncPublishedContacts failed', [
                'job_posting_id' => $this->jobPostingId,
                'exception' => $e,
            ]);
        }
    }
}
