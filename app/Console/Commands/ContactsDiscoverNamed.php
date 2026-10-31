<?php

namespace App\Console\Commands;

use App\Contacts\Actions\DiscoverNamedContacts;
use App\Contacts\Jobs\DiscoverNamedContactsForCompany;
use App\Contacts\Support\OutreachSuppressionList;
use App\Enums\OutreachStatus;
use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ContactsDiscoverNamed extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'contacts:discover-named
        {--limit= : Maximum number of companies to queue}
        {--force : Actually queue the companies}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Count verified, non-catch-all companies due for named-contact discovery (dry run) or queue them';

    public function handle(OutreachSuppressionList $suppressions): int
    {
        $limit = $this->option('limit');

        if ($limit !== null && preg_match('/^[1-9]\d*$/', (string) $limit) !== 1) {
            $this->error('Invalid --limit: use a positive integer.');

            return self::FAILURE;
        }

        $limit = $limit !== null ? (int) $limit : null;
        $force = (bool) $this->option('force');
        $selected = 0;

        $this->candidates()
            ->chunkById(200, function (Collection $companies) use ($suppressions, $limit, $force, &$selected): bool {
                foreach ($companies as $company) {
                    if ($limit !== null && $selected >= $limit) {
                        return false;
                    }

                    if ($suppressions->coversDomain((string) $company->domain)) {
                        continue;
                    }

                    if ($force) {
                        DiscoverNamedContactsForCompany::dispatch($company->id);
                    }

                    $selected++;
                }

                return true;
            });

        if (! $force) {
            $this->line("Dry run: {$selected} company(ies) would be queued. Pass --force to apply.");

            return self::SUCCESS;
        }

        $this->info("Queued {$selected} company(ies) for named-contact discovery.");

        return self::SUCCESS;
    }

    /**
     * @return Builder<Company>
     */
    private function candidates(): Builder
    {
        return Company::query()
            ->where('outreach_status', OutreachStatus::Verified)
            ->whereNotNull('domain')
            ->where('is_catch_all', false)
            ->where(fn (Builder $query) => $query
                ->whereNull('named_contacts_checked_at')
                ->orWhere('named_contacts_checked_at', '<', now()->subDays(DiscoverNamedContacts::RECHECK_DAYS)));
    }
}
