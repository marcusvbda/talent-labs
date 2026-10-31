<?php

namespace App\Console\Commands;

use App\Reports\TierPerformance;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class TierPerformanceReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:tier-performance {--days=30} {--json}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Print sends, replies and reply rate per recipient tier';

    public function handle(TierPerformance $performance): int
    {
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 365]]);

        if ($days === false) {
            $this->error('The --days option must be an integer between 1 and 365.');

            return self::FAILURE;
        }

        $report = $performance->build($days);

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));

            return self::SUCCESS;
        }

        $since = CarbonImmutable::parse($report['since'])->format('j M Y H:i');
        $this->line("Tier performance — last {$report['window_days']} days (since {$since})");

        $this->newLine();
        $this->table(['Tier', 'Sent', 'Replies', 'Reply rate'], array_map(
            fn (array $row): array => [
                $row['tier'] === 'total' ? 'Total' : $row['tier'],
                $row['sent'],
                $row['replies'],
                sprintf('%.1f%%', $row['reply_rate']),
            ],
            [...$report['tiers'], $report['totals']],
        ));

        $this->line('Replies are client-reported, so the counts are a minimum.');

        return self::SUCCESS;
    }
}
