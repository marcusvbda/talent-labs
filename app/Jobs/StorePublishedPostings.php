<?php

namespace App\Jobs;

use App\Actions\Collection\StoreNewPostings;
use App\Ai\Jobs\ExtractJobPostingProfileJob;
use App\Collection\Support\PublishedEmailExtractor;
use App\Contacts\Actions\RecordPublishedContacts;
use App\Contacts\Actions\VerifyPublishedEmails;
use App\Enums\ContactConfidence;
use App\Models\Company;
use App\Models\JobPosting;
use App\Models\SourceRun;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Marcusvbda\FilamentRealtimeDriver\RealtimeEvent;
use Throwable;

/**
 * Stores the new postings of a company not verified for sending that publish an email
 * the probe doesn't reject, and records those emails as the postings' contacts.
 */
class StorePublishedPostings implements ShouldQueue
{
    use Batchable, Queueable;

    /**
     * Every release by WithoutOverlapping counts as an attempt: these cover the wait
     * (5 x 15s) for another job of the same company. Failures are caught in handle().
     */
    public int $tries = 6;

    /**
     * Must stay below the database queue's retry_after (90).
     */
    public int $timeout = 75;

    /**
     * The extra tries are only for lock waits, never to redo a timed-out probe.
     */
    public bool $failOnTimeout = true;

    /**
     * @param  list<array<string, mixed>>  $rows  new postings in column form, without company_id
     */
    public function __construct(
        public int $sourceRunId,
        public int $companyId,
        public array $rows,
    ) {}

    /**
     * Never probes in parallel with another StorePublishedPostings or StoreCompanyPostings of the company.
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->companyId))->releaseAfter(15)->expireAfter(120)->shared()];
    }

    /**
     * The emails a row in column form publishes (its `source_contacts` is JSON-encoded or null).
     *
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    public static function emailsOfRow(array $row): array
    {
        $contacts = $row['source_contacts'] ?? null;

        if (is_string($contacts)) {
            $contacts = json_decode($contacts, true);
        }

        if (! is_array($contacts)) {
            return [];
        }

        $entries = [];

        foreach ($contacts as $contact) {
            if (is_array($contact) && is_string($contact['email'] ?? null)) {
                $entries[] = ['kind' => (string) ($contact['kind'] ?? ''), 'email' => $contact['email']];
            }
        }

        return PublishedEmailExtractor::emailsOf($entries);
    }

    public function handle(StoreNewPostings $store, VerifyPublishedEmails $verify, RecordPublishedContacts $record): void
    {
        try {
            if ($this->batch()?->cancelled()) {
                return;
            }

            $company = Company::find($this->companyId);
            $sourceRun = SourceRun::find($this->sourceRunId);

            if ($company === null || $sourceRun === null) {
                return;
            }

            /** @var array<string, list<string>> $emailsByRow */
            $emailsByRow = [];

            /** @var array<string, array<string, mixed>> $rows */
            $rows = [];

            foreach ($this->rows as $row) {
                if (($row['ineligible_reason'] ?? null) !== null) {
                    continue;
                }

                $emails = array_values(array_filter(
                    self::emailsOfRow($row),
                    fn (string $email): bool => PublishedEmailExtractor::matchesCompany($email, $company),
                ));

                if ($emails !== []) {
                    $externalId = (string) $row['external_id'];
                    $emailsByRow[$externalId] = $emails;
                    $rows[$externalId] = $row;
                }
            }

            if ($rows === []) {
                return;
            }

            $verdicts = $verify->handle($company, array_values(array_unique(array_merge(...array_values($emailsByRow)))));

            if ($verdicts === []) {
                return;
            }

            $storable = [];
            $rejectedUnstored = [];

            foreach ($emailsByRow as $externalId => $emails) {
                $sendable = array_filter(
                    $emails,
                    fn (string $email): bool => isset($verdicts[$email]) && $verdicts[$email] !== ContactConfidence::Rejected,
                );

                if ($sendable !== []) {
                    $storable[] = $rows[$externalId];

                    continue;
                }

                foreach ($emails as $email) {
                    if (($verdicts[$email] ?? null) === ContactConfidence::Rejected) {
                        $rejectedUnstored[$email] = ContactConfidence::Rejected;
                    }
                }
            }

            $ids = $storable === [] ? [] : $store->handle($company, $sourceRun, $storable);

            foreach ($storable as $row) {
                $externalId = (string) $row['external_id'];
                $posting = isset($ids[$externalId]) ? JobPosting::find($ids[$externalId]) : null;

                if ($posting === null) {
                    continue;
                }

                $record->handle($company, $posting, array_intersect_key($verdicts, array_flip($emailsByRow[$externalId])));

                // StoreNewPostings already queued the profile job, but it may have run before the contact
                // existed and returned at the gate: queue it again now (a still-locked one is dropped as a duplicate).
                if ($posting->hasSendableTierA() && JobPosting::query()->whereKey($posting->id)->needingProfile()->exists()) {
                    ExtractJobPostingProfileJob::dispatch($posting->id);
                }
            }

            // Company-level verdict rows, so these emails aren't probed again within the TTL.
            $record->handle($company, null, $rejectedUnstored);

            // Contacts surface on the company pages: announce the change once.
            try {
                RealtimeEvent::dispatch('companies', 'CompanyUpdated', ['id' => $company->id]);
            } catch (BroadcastException $e) {
                report($e);
            }
        } catch (Throwable $e) {
            Log::warning('StorePublishedPostings failed', [
                'source_run_id' => $this->sourceRunId,
                'company_id' => $this->companyId,
                'exception' => $e,
            ]);
        }
    }
}
