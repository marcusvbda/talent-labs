<?php

namespace App\Console\Commands;

use App\Enums\ApplicationStatus;
use App\Models\User;
use App\Reports\ApplicationAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * @phpstan-import-type AuditRow from ApplicationAudit
 * @phpstan-import-type AuditSummary from ApplicationAudit
 */
class ApplicationAuditReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:application-audit {--status=sent,queued} {--user=} {--json}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit sent/queued applications for quality flags';

    public function handle(ApplicationAudit $audit): int
    {
        $statuses = [];

        foreach (explode(',', (string) $this->option('status')) as $value) {
            $value = trim($value);

            if ($value === '') {
                continue;
            }

            $status = ApplicationStatus::tryFrom($value);

            if ($status === null) {
                $this->error("Unknown status: {$value}.");

                return self::FAILURE;
            }

            $statuses[$status->value] = $status;
        }

        if ($statuses === []) {
            $this->error('Unknown status: (empty).');

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

        $report = $audit->build(array_values($statuses), $user);
        $summary = $report['summary'];

        if ($summary['total'] === 0) {
            $this->line('No applications to audit.');

            return self::SUCCESS;
        }

        $directory = storage_path('app/private/reports');
        File::ensureDirectoryExists($directory);

        $base = $directory.'/application-audit-'.now()->format('Ymd-His');
        $csvPath = "{$base}.csv";
        $mdPath = "{$base}.md";

        $this->writeCsv($csvPath, $report['rows']);
        File::put($mdPath, $this->markdown($summary));

        if ($this->option('json')) {
            $this->line((string) json_encode([...$summary, 'files' => ['csv' => $csvPath, 'md' => $mdPath]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->printSummary($summary);

        $this->newLine();
        $this->line("CSV: {$csvPath}");
        $this->line("Summary: {$mdPath}");

        return self::SUCCESS;
    }

    /**
     * @param  list<AuditRow>  $rows
     */
    private function writeCsv(string $path, array $rows): void
    {
        $handle = fopen($path, 'w');

        if ($handle === false) {
            throw new \RuntimeException("Cannot open {$path} for writing.");
        }

        fputcsv($handle, ApplicationAudit::COLUMNS, escape: '');

        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn (string $column): string => (string) $row[$column], ApplicationAudit::COLUMNS), escape: '');
        }

        fclose($handle);
    }

    /**
     * @param  AuditSummary  $summary
     */
    private function printSummary(array $summary): void
    {
        $this->line("Application audit — {$summary['generated_at']}");
        $this->line('Statuses: '.implode(', ', $summary['statuses']).($summary['user_id'] === null ? '' : " · user #{$summary['user_id']}"));

        $this->table(['Status', 'Applications'], [
            ...array_map(fn (string $status, int $count): array => [$status, $count], array_keys($summary['by_status']), $summary['by_status']),
            ['Total', $summary['total']],
            ['Posting deleted', $summary['posting_deleted']],
        ]);

        $this->table(['Flag', 'Count', '%'], [
            ...array_map(fn (array $flag): array => [$flag['label'], $flag['count'], "{$this->pct($flag['percent'])}%"], array_values($summary['flags'])),
            ['2+ flags', $summary['two_or_more_flags']['count'], "{$this->pct($summary['two_or_more_flags']['percent'])}%"],
            ['No flag', $summary['no_flags']['count'], "{$this->pct($summary['no_flags']['percent'])}%"],
        ]);

        foreach ($summary['flags'] as $flag) {
            if ($flag['count'] === 0) {
                continue;
            }

            $this->newLine();
            $this->line("{$flag['label']} ({$flag['count']}, {$this->pct($flag['percent'])}%)");
            $this->table(['Top sources', 'Count'], array_map(fn (array $item): array => [$item['name'], $item['count']], $flag['top_sources']));
            $this->table(['Top destination domains', 'Count'], array_map(fn (array $item): array => [$item['name'], $item['count']], $flag['top_domains']));
            $this->table(['Application', 'Posting title', 'Subject', 'Reason'], array_map(fn (array $example): array => [
                $example['application_id'],
                $example['posting_title'],
                $example['subject'],
                $example['reason'],
            ], $flag['examples']));
        }
    }

    /**
     * @param  AuditSummary  $summary
     */
    private function markdown(array $summary): string
    {
        $cell = fn (string|int|float $value): string => str_replace(['|', "\n", "\r"], ['\\|', ' ', ' '], (string) $value);

        $lines = [
            '# Application audit',
            '',
            "- Generated at: {$summary['generated_at']}",
            '- Statuses: '.implode(', ', $summary['statuses']),
            '- User: '.($summary['user_id'] === null ? 'all' : "#{$summary['user_id']}"),
            "- Total: {$summary['total']}",
            "- Posting deleted: {$summary['posting_deleted']}",
            ...array_map(fn (string $status, int $count): string => "- {$status}: {$count}", array_keys($summary['by_status']), $summary['by_status']),
            '',
            '| Flag | Count | % |',
            '| ---- | ----- | - |',
            ...array_map(fn (array $flag): string => "| {$flag['label']} | {$flag['count']} | {$this->pct($flag['percent'])}% |", array_values($summary['flags'])),
            "| 2+ flags | {$summary['two_or_more_flags']['count']} | {$this->pct($summary['two_or_more_flags']['percent'])}% |",
            "| No flag | {$summary['no_flags']['count']} | {$this->pct($summary['no_flags']['percent'])}% |",
        ];

        foreach ($summary['flags'] as $flag) {
            $lines[] = '';
            $lines[] = "## {$flag['label']} ({$flag['count']}, {$this->pct($flag['percent'])}%)";

            if ($flag['count'] === 0) {
                continue;
            }

            $lines[] = '';
            $lines[] = '| Top sources | Count |';
            $lines[] = '| ----------- | ----- |';

            foreach ($flag['top_sources'] as $item) {
                $lines[] = "| {$cell($item['name'])} | {$item['count']} |";
            }

            $lines[] = '';
            $lines[] = '| Top destination domains | Count |';
            $lines[] = '| ----------------------- | ----- |';

            foreach ($flag['top_domains'] as $item) {
                $lines[] = "| {$cell($item['name'])} | {$item['count']} |";
            }

            $lines[] = '';
            $lines[] = '| Application | Posting title | Subject | Reason |';
            $lines[] = '| ----------- | ------------- | ------- | ------ |';

            foreach ($flag['examples'] as $example) {
                $lines[] = "| {$example['application_id']} | {$cell($example['posting_title'])} | {$cell($example['subject'])} | {$cell($example['reason'])} |";
            }
        }

        return implode("\n", $lines)."\n";
    }

    private function pct(float|int $percent): string
    {
        return number_format((float) $percent, 1);
    }
}
