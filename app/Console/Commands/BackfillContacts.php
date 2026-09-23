<?php

namespace App\Console\Commands;

use App\Contacts\Jobs\DiscoverContactsForPosting;
use App\Models\JobPosting;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class BackfillContacts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'contacts:backfill';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue contact discovery for job postings without a company';

    public function handle(): int
    {
        $queued = 0;

        JobPosting::query()
            ->whereNull('company_id')
            ->select('id')
            ->chunkById(200, function (Collection $postings) use (&$queued): void {
                foreach ($postings as $posting) {
                    DiscoverContactsForPosting::dispatch($posting->id);
                    $queued++;
                }
            }, 'id');

        $this->info("Queued {$queued} postings for contact discovery.");

        return self::SUCCESS;
    }
}
