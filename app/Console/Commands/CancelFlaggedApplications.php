<?php

namespace App\Console\Commands;

use App\Enums\ApplicationStatus;
use App\Events\Client\SendingUpdated;
use App\Models\Application;
use App\Reports\ApplicationAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CancelFlaggedApplications extends Command
{
    private const string CANCEL_REASON = 'Cancelled by the owner (quality audit).';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'applications:cancel-flagged
        {--ids= : Comma-separated application ids to cancel (requires --force)}
        {--force : Actually cancel the given ids}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List queued applications with quality flags (dry run) or cancel the confirmed ids';

    public function handle(ApplicationAudit $audit): int
    {
        $ids = $this->option('ids');

        if ($ids === null || $ids === '') {
            return $this->dryRun($audit);
        }

        if (preg_match('/^\d+(,\d+)*$/', $ids) !== 1) {
            $this->error('Invalid --ids: use comma-separated application ids, e.g. --ids=1,2,3.');

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            $this->error('Pass --force to cancel.');

            return self::FAILURE;
        }

        return $this->cancel(array_values(array_unique(array_map('intval', explode(',', $ids)))));
    }

    /**
     * Read-only: prints the flagged queued applications and their ids.
     */
    private function dryRun(ApplicationAudit $audit): int
    {
        $rows = array_values(array_filter(
            $audit->build([ApplicationStatus::Queued])['rows'],
            fn (array $row): bool => $row['flags_count'] >= 1,
        ));

        if ($rows === []) {
            $this->info('No flagged queued applications.');

            return self::SUCCESS;
        }

        $this->table(
            ['application_id', 'user_id', 'posting_title', 'subject', 'flags'],
            array_map(fn (array $row): array => [
                $row['application_id'],
                $row['user_id'],
                $row['posting_title'],
                $row['subject'],
                implode(', ', array_keys(array_filter(
                    array_intersect_key($row, ApplicationAudit::FLAGS),
                    fn (int $value): bool => $value === 1,
                ))),
            ], $rows),
        );

        $this->line(count($rows).' flagged queued application(s).');
        $this->line('Ids: '.implode(',', array_column($rows, 'application_id')));

        return self::SUCCESS;
    }

    /**
     * Cancels the given ids that are still queued; anything else is skipped and listed.
     *
     * @param  list<int>  $ids
     */
    private function cancel(array $ids): int
    {
        /** @var list<int> $cancelled */
        $cancelled = [];
        /** @var array<int, int> $userIds */
        $userIds = [];

        DB::transaction(function () use ($ids, &$cancelled, &$userIds): void {
            $applications = Application::query()
                ->whereKey($ids)
                ->where('status', ApplicationStatus::Queued)
                ->lockForUpdate()
                ->get();

            foreach ($applications as $application) {
                $application->update([
                    'status' => ApplicationStatus::Cancelled,
                    'last_error' => self::CANCEL_REASON,
                ]);

                $cancelled[] = $application->id;
                $userIds[$application->user_id] = $application->user_id;
            }
        });

        foreach ($userIds as $userId) {
            SendingUpdated::broadcastFor($userId);
        }

        $skipped = array_values(array_diff($ids, $cancelled));

        $this->info('Cancelled: '.count($cancelled));
        $this->line('Skipped: '.count($skipped));

        if ($skipped !== []) {
            $this->line('Skipped ids (not queued or not found): '.implode(',', $skipped));
        }

        return self::SUCCESS;
    }
}
