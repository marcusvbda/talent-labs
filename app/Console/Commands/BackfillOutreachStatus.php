<?php

namespace App\Console\Commands;

use App\Enums\ContactConfidence;
use App\Enums\ContactStatus;
use App\Enums\OutreachStatus;
use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BackfillOutreachStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'companies:backfill-outreach-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Derive outreach status for companies already through contact discovery';

    public function handle(): int
    {
        $counts = collect(OutreachStatus::cases())
            ->mapWithKeys(fn (OutreachStatus $status): array => [$status->value => 0])
            ->all();
        $updated = 0;

        Company::query()
            ->where('outreach_status', OutreachStatus::Pending)
            ->where('contact_status', '!=', ContactStatus::Pending)
            ->withExists(['contacts as has_verified_contact' => fn (Builder $query) => $query->where('confidence', ContactConfidence::SmtpVerified)])
            ->chunkById(200, function (Collection $companies) use (&$counts, &$updated): void {
                foreach ($companies as $company) {
                    /** @var Company $company */
                    $status = match (true) {
                        $company->contact_status === ContactStatus::NoDomain => OutreachStatus::NoDomain,
                        (bool) $company->getAttribute('has_verified_contact') => OutreachStatus::Verified,
                        default => OutreachStatus::NotVerifiable,
                    };

                    $company->outreach_status = $status;
                    $company->save();

                    $counts[$status->value]++;
                    $updated++;
                }
            }, 'id');

        $this->info("Updated {$updated} companies.");

        foreach ($counts as $status => $count) {
            $this->line("  {$status}: {$count}");
        }

        return self::SUCCESS;
    }
}
