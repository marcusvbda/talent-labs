<?php

namespace App\Console\Commands;

use App\Ai\Jobs\ExtractJobPostingProfileJob;
use App\Enums\OutreachStatus;
use App\Models\JobPosting;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ExtractPostingProfiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'postings:extract-profiles {--limit= : Only queue this many postings}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue AI profile extraction for postings of verified companies';

    public function handle(): int
    {
        $limit = $this->option('limit');

        if ($limit !== null && filter_var($limit, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            $this->error('The --limit option must be a positive integer.');

            return self::FAILURE;
        }

        $query = JobPosting::query()
            ->whereHas('company', fn (Builder $company) => $company->where('outreach_status', OutreachStatus::Verified))
            ->needingProfile();

        $queued = 0;

        if ($limit !== null) {
            $query->orderBy('id')
                ->limit((int) $limit)
                ->pluck('id')
                ->each(function (int $id) use (&$queued): void {
                    ExtractJobPostingProfileJob::dispatch($id);
                    $queued++;
                });
        } else {
            $query->select('id')->chunkById(200, function (Collection $postings) use (&$queued): void {
                foreach ($postings as $posting) {
                    /** @var JobPosting $posting */
                    ExtractJobPostingProfileJob::dispatch($posting->id);
                    $queued++;
                }
            });
        }

        $this->info("Queued {$queued} postings for profile extraction.");

        return self::SUCCESS;
    }
}
