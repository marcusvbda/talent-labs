<?php

namespace App\Console\Commands;

use App\Reports\NamedContactCoverage;
use Illuminate\Console\Command;

class NamedContactCoverageReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:named-contact-coverage {--sample=50} {--input-usd-per-mtok=} {--output-usd-per-mtok=} {--json}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Estimate named-contact coverage on a sample of verified companies (probes, stores nothing)';

    public function handle(): int
    {
        $sample = filter_var($this->option('sample'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($sample === false) {
            $this->error('The --sample option must be a positive integer.');

            return self::FAILURE;
        }

        $prices = [];

        foreach (['input-usd-per-mtok', 'output-usd-per-mtok'] as $option) {
            $value = $this->option($option);

            if ($value === null) {
                $prices[$option] = null;

                continue;
            }

            $price = filter_var($value, FILTER_VALIDATE_FLOAT);

            if ($price === false || $price < 0) {
                $this->error("The --{$option} option must be a non-negative number.");

                return self::FAILURE;
            }

            $prices[$option] = $price;
        }

        // Resolved once for the whole run: the page fetcher's per-domain spacing lives on the instance.
        $report = app(NamedContactCoverage::class)->build($sample, $prices['input-usd-per-mtok'], $prices['output-usd-per-mtok']);

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $totals = $report['totals'];
        $this->line("Named-contact coverage — {$totals['companies']} companies sampled (requested {$report['sample_requested']})");

        $this->newLine();
        $rows = array_map(fn (array $source): array => $this->row($source['name'], $source), $report['sources']);
        $rows[] = $this->row('Total', $totals);
        $this->table(['Source', 'Companies', 'Plausible name', 'Verified named email', 'Catch-all', 'Probed', 'Inconclusive'], $rows);

        $tokens = $report['tokens'];
        $this->newLine();
        $this->line("Agent calls: {$tokens['agent_calls']} ({$tokens['failed_calls']} failed) · input tokens: {$tokens['input']} · output tokens: {$tokens['output']}");
        $this->line($report['usd'] === null
            ? 'USD: not computed (pass both --input-usd-per-mtok and --output-usd-per-mtok)'
            : sprintf('USD: %.4f', $report['usd']));

        if ($report['port25_warning']) {
            $this->warn('Warning: every SMTP session was inconclusive; outbound port 25 is probably blocked.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  array{companies: int, with_name: int, with_verified_named_email: int, catch_all: int, probed: int, inconclusive: int}  $counts
     * @return list<int|string>
     */
    private function row(string $name, array $counts): array
    {
        $companies = $counts['companies'];

        return [
            $name,
            $companies,
            $this->withPercent($counts['with_name'], $companies),
            $this->withPercent($counts['with_verified_named_email'], $companies),
            $this->withPercent($counts['catch_all'], $companies),
            $counts['probed'],
            $this->withPercent($counts['inconclusive'], $counts['probed']),
        ];
    }

    private function withPercent(int $count, int $of): string
    {
        return $count.' ('.($of > 0 ? round($count / $of * 100) : 0).'%)';
    }
}
