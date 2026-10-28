<?php

namespace App\Console\Commands;

use App\Contacts\Actions\RetryCompanyDiscovery;
use App\Enums\ContactStatus;
use App\Enums\DomainStatus;
use App\Enums\OutreachStatus;
use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

class ContactsRecheck extends Command
{
    private const array SCOPES = ['domains', 'contacts', 'all'];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'contacts:recheck
        {--scope=all : domains|contacts|all}
        {--limit= : Maximum number of companies to queue in this run}
        {--force : Actually reset and queue the companies}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List companies whose domain/contact verdict may be a false negative (dry run) or re-queue their discovery';

    public function handle(RetryCompanyDiscovery $retry): int
    {
        $scope = (string) $this->option('scope');

        if (! in_array($scope, self::SCOPES, true)) {
            $this->error('Invalid --scope: use domains, contacts or all.');

            return self::FAILURE;
        }

        $limit = $this->option('limit');

        if ($limit !== null && preg_match('/^[1-9]\d*$/', (string) $limit) !== 1) {
            $this->error('Invalid --limit: use a positive integer.');

            return self::FAILURE;
        }

        $limit = $limit !== null ? (int) $limit : null;

        $domains = $scope !== 'contacts' ? $this->candidates('domains')->count() : 0;
        $contacts = $scope !== 'domains' ? $this->candidates('contacts')->count() : 0;
        $total = $domains + $contacts;
        $toQueue = $limit !== null ? min($limit, $total) : $total;

        $this->table(['scope', 'candidates'], [
            ['domains (not found)', $domains],
            ['contacts (not verifiable, no catch-all verdict or none found)', $contacts],
            ['total', $total],
        ]);

        if (! $this->option('force')) {
            $this->line("Dry run: {$toQueue} company(ies) would be reset and queued. Pass --force to apply.");

            return self::SUCCESS;
        }

        $queued = 0;

        $this->candidates($scope)
            ->chunkById(200, function (Collection $companies) use ($retry, $limit, &$queued): bool {
                foreach ($companies as $company) {
                    if ($limit !== null && $queued >= $limit) {
                        return false;
                    }

                    $retry->handle($company);
                    $queued++;
                }

                return true;
            });

        $this->info("Queued {$queued} company(ies) for discovery.");

        return self::SUCCESS;
    }

    /**
     * Companies to recheck, never ones with an application (queued/sent outreach stays untouched).
     *
     * @return Builder<Company>
     */
    private function candidates(string $scope): Builder
    {
        // A company just rechecked (fresh verdict) is left alone, so repeated --limit runs advance.
        $cutoff = now()->subMinutes(30);

        return Company::query()
            ->whereNotExists(fn (QueryBuilder $query) => $query->selectRaw('1')
                ->from('applications')
                ->whereColumn('applications.company_id', 'companies.id'))
            ->where(function (Builder $query) use ($scope, $cutoff): void {
                if ($scope !== 'contacts') {
                    $query->orWhere(fn (Builder $query) => $query
                        ->where('domain_status', DomainStatus::NotFound)
                        ->where(fn (Builder $query) => $query
                            ->whereNull('domain_checked_at')
                            ->orWhere('domain_checked_at', '<', $cutoff)));
                }

                if ($scope !== 'domains') {
                    $query->orWhere(fn (Builder $query) => $query
                        ->where('domain_status', DomainStatus::Found)
                        ->where('outreach_status', OutreachStatus::NotVerifiable)
                        ->where(fn (Builder $query) => $query
                            ->whereNull('contact_checked_at')
                            ->orWhere('contact_checked_at', '<', $cutoff))
                        ->where(fn (Builder $query) => $query
                            ->whereNull('is_catch_all')
                            ->orWhere('is_catch_all', false))
                        ->where(fn (Builder $query) => $query
                            ->where('contact_status', ContactStatus::NotFound)
                            ->orWhereNull('is_catch_all')));
                }
            });
    }
}
