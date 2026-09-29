<?php

namespace App\Console\Commands;

use App\Enums\ContactConfidence;
use App\Enums\OutreachStatus;
use App\Models\User;
use App\Reports\InventoryFunnel;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class FunnelReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:funnel {--days=30} {--user=} {--json}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Print the collection → verified pool funnel (per source, global, and a client\'s runway)';

    public function handle(InventoryFunnel $funnel): int
    {
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 365]]);

        if ($days === false) {
            $this->error('The --days option must be an integer between 1 and 365.');

            return self::FAILURE;
        }

        $user = null;
        $userId = $this->option('user');

        if ($userId !== null) {
            // Non-numeric ids would make PostgreSQL reject the query, so they are simply not found.
            $user = ctype_digit((string) $userId) ? User::query()->find((int) $userId) : null;

            if (! $user instanceof User) {
                $this->error("User #{$userId} not found.");

                return self::FAILURE;
            }
        }

        $report = $funnel->build($days, $user);

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $since = CarbonImmutable::parse($report['since'])->format('j M Y H:i');
        $this->line("Funnel — last {$report['window_days']} days (since {$since})");

        $this->newLine();
        $this->line('Per source');
        $rows = array_map(fn (array $source): array => $this->sourceRow($source['name'], $source['is_active'] ? 'yes' : 'no', $source), $report['sources']);
        $rows[] = $this->sourceRow('Total', '', $report['totals']);
        $this->table(
            ['Source', 'Active', 'Fetched', 'New', 'Postings', 'Target', 'Company', 'Domain', 'Verified', 'Profile done', 'EN', 'PT', 'Other'],
            $rows,
        );

        $companies = $report['global']['companies_by_outreach_status'];
        $this->newLine();
        $this->line('Companies by outreach status');
        $this->table(['Status', 'Companies'], [
            ...array_map(fn (OutreachStatus $status): array => [$status->getLabel(), $companies[$status->value]], OutreachStatus::cases()),
            ['Total', $companies['total']],
        ]);

        $contacts = $report['global']['contacts_by_confidence'];
        $this->newLine();
        $this->line('Contacts by confidence');
        $this->table(['Confidence', 'Contacts', 'Share'], [
            ...array_map(fn (ContactConfidence $confidence): array => [
                $confidence->getLabel(),
                $contacts[$confidence->value],
                $this->percent($contacts[$confidence->value], $contacts['total']),
            ], ContactConfidence::cases()),
            ['Total', $contacts['total'], $this->percent($contacts['total'], $contacts['total'])],
        ]);

        if ($report['global']['port25_warning']) {
            $pct = round($report['global']['mx_only_share'] * 100);
            $this->warn("Warning: outbound port 25 is probably blocked ({$pct}% of contacts are mx_only).");
        }

        $client = $report['user'];

        if ($client !== null) {
            $languages = $client['active_languages'] === [] ? 'none' : implode(', ', $client['active_languages']);

            $this->newLine();
            $this->line("Client #{$client['id']} · {$client['name']} · {$client['plan']} ({$client['daily_limit']}/day)");
            $this->table(['Language', 'Pool (companies)'], [
                ['EN', $client['pool']['en']],
                ['PT', $client['pool']['pt']],
                ["Applicable now ({$languages})", $client['pool']['total']],
            ]);
            $this->line(sprintf('Runway: %.1f days (pool / daily limit)', $client['runway_days']));
        }

        return self::SUCCESS;
    }

    /**
     * @param  array{fetched: int, new: int, postings: int, target_family: int, with_company: int, with_domain: int, verified: int, profile_done: int, lang: array{en: int, pt: int, other: int}}  $counts
     * @return list<int|string>
     */
    private function sourceRow(string $name, string $active, array $counts): array
    {
        $postings = $counts['postings'];

        return [
            $name,
            $active,
            $counts['fetched'],
            $counts['new'],
            $postings,
            $this->withPercent($counts['target_family'], $postings),
            $this->withPercent($counts['with_company'], $postings),
            $this->withPercent($counts['with_domain'], $postings),
            $this->withPercent($counts['verified'], $postings),
            $this->withPercent($counts['profile_done'], $postings),
            $counts['lang']['en'],
            $counts['lang']['pt'],
            $counts['lang']['other'],
        ];
    }

    private function withPercent(int $count, int $of): string
    {
        return "{$count} ({$this->percent($count, $of)})";
    }

    private function percent(int $count, int $of): string
    {
        return ($of > 0 ? round($count / $of * 100) : 0).'%';
    }
}
