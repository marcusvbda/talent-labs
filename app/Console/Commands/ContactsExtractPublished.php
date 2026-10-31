<?php

namespace App\Console\Commands;

use App\Collection\Data\SourceContactData;
use App\Collection\Support\PublishedEmailExtractor;
use App\Contacts\Jobs\SyncPublishedContacts;
use App\Enums\ContactSource;
use App\Enums\SourceContactKind;
use App\Models\Contact;
use App\Models\JobPosting;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Marcusvbda\FilamentRealtimeDriver\RealtimeEvent;

class ContactsExtractPublished extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'contacts:extract-published
        {--limit= : Maximum number of postings to queue in this run}
        {--force : Actually update and queue the postings}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill the emails stored postings publish (dry run) or store them and queue their posting contacts';

    public function handle(): int
    {
        $limit = $this->option('limit');

        if ($limit !== null && preg_match('/^[1-9]\d*$/', (string) $limit) !== 1) {
            $this->error('Invalid --limit: use a positive integer.');

            return self::FAILURE;
        }

        $limit = $limit !== null ? (int) $limit : null;
        $force = (bool) $this->option('force');

        $scanned = 0;
        $withEmails = 0;
        $newEmails = 0;
        $candidates = 0;
        $queued = 0;
        $updated = 0;

        JobPosting::query()
            ->whereNull('ineligible_reason')
            ->whereNotNull('company_id')
            ->with('company')
            ->select(['id', 'company_id', 'source_contacts', 'description_html', 'description_text', 'apply_url'])
            ->chunkById(200, function (Collection $postings) use ($force, $limit, &$scanned, &$withEmails, &$newEmails, &$candidates, &$queued, &$updated): bool {
                $withContact = Contact::query()
                    ->where('source', ContactSource::Posting)
                    ->whereIn('job_posting_id', $postings->modelKeys())
                    ->distinct()
                    ->pluck('job_posting_id')
                    ->all();

                /** @var JobPosting $posting */
                foreach ($postings as $posting) {
                    if ($force && $limit !== null && $queued >= $limit) {
                        return false;
                    }

                    $scanned++;
                    $company = $posting->company;

                    if ($company === null) {
                        continue;
                    }

                    $stored = array_map('mb_strtolower', PublishedEmailExtractor::emailsOf($posting->source_contacts));

                    // Same discard rules (extract) and company domain match as the collection path.
                    $matching = array_values(array_filter(
                        PublishedEmailExtractor::extract($posting->description_html, $posting->description_text, $posting->apply_url),
                        fn (string $email): bool => PublishedEmailExtractor::matchesCompany($email, $company),
                    ));

                    $new = array_values(array_diff($matching, $stored));

                    $hasEmails = $new !== [] || array_filter(
                        $stored,
                        fn (string $email): bool => PublishedEmailExtractor::matchesCompany($email, $company),
                    ) !== [];

                    if (! $hasEmails) {
                        continue;
                    }

                    $withEmails++;
                    $newEmails += count($new);

                    if ($new === [] && in_array($posting->id, $withContact, true)) {
                        continue;
                    }

                    $candidates++;

                    if (! $force) {
                        continue;
                    }

                    if ($new !== []) {
                        $this->storeEmails($posting, $new);
                        $updated++;
                    }

                    SyncPublishedContacts::dispatch($posting->id);
                    $queued++;
                }

                return true;
            });

        $this->table(['scanned', 'with emails', 'new emails', 'would queue'], [
            [$scanned, $withEmails, $newEmails, $limit !== null ? min($limit, $candidates) : $candidates],
        ]);

        if (! $force) {
            $wouldQueue = $limit !== null ? min($limit, $candidates) : $candidates;
            $this->line("Dry run: {$wouldQueue} posting(s) would be updated and queued. Pass --force to apply.");

            return self::SUCCESS;
        }

        if ($updated > 0) {
            // The query-builder update skips model events: announce the updated postings once.
            try {
                RealtimeEvent::dispatch('job_postings', 'JobPostingsUpdated', []);
            } catch (BroadcastException $e) {
                report($e);
            }
        }

        $this->info("Queued {$queued} posting(s).");

        return self::SUCCESS;
    }

    /**
     * Append the new emails to the stored source contacts, keeping every existing entry and
     * leaving `collection_run_id`, `first_seen_at` and `company_id` untouched.
     *
     * @param  list<string>  $emails
     */
    private function storeEmails(JobPosting $posting, array $emails): void
    {
        $contacts = $posting->source_contacts ?? [];

        foreach ($emails as $email) {
            $contacts[] = (new SourceContactData(SourceContactKind::Email, email: $email))->toArray();
        }

        DB::table('job_postings')
            ->where('id', $posting->id)
            ->update([
                'source_contacts' => json_encode($contacts, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
    }
}
