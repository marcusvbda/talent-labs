<?php

namespace App\Console\Commands;

use App\Enums\ApplicationOrigin;
use App\Enums\ApplicationStatus;
use App\Enums\UserStatus;
use App\Models\User;
use App\Outreach\Actions\CanSendApplications;
use App\Outreach\Actions\QueueApplication;
use App\Outreach\OutreachLimits;
use App\Outreach\Queries\MatchingJobPostings;
use App\Outreach\Support\SendScheduler;
use App\Plans\Plan;
use App\Plans\PlanCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DispatchAutoApplications extends Command
{
    private const int CANDIDATE_LIMIT = 20;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'outreach:dispatch-auto';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue one application for each eligible auto-mode user with nothing queued or sending';

    public function handle(
        PlanCatalog $plans,
        CanSendApplications $eligibility,
        SendScheduler $scheduler,
        QueueApplication $queueApplication,
    ): int {
        $autoPlanKeys = collect($plans->all())
            ->filter(fn (Plan $plan): bool => $plan->mode === 'auto')
            ->map(fn (Plan $plan): string => $plan->key->value)
            ->values()
            ->all();

        $usersConsidered = 0;
        $queuedCount = 0;
        $skippedCount = 0;

        if ($autoPlanKeys !== []) {
            $users = User::query()
                ->whereIn('plan_key', $autoPlanKeys)
                ->where('status', UserStatus::Active)
                ->whereNull('sending_paused_at')
                ->whereDoesntHave('applications', fn ($query) => $query->whereIn('status', [
                    ApplicationStatus::Queued,
                    ApplicationStatus::Sending,
                ]))
                ->lazyById(100);

            foreach ($users as $user) {
                /** @var User $user */
                $usersConsidered++;

                $check = $eligibility->check($user);

                if (! $check->ok() || $check->remaining <= 0 || ! $scheduler->isInsideWindow($user)) {
                    $skippedCount++;

                    continue;
                }

                $candidates = MatchingJobPostings::forUser($user)
                    ->where('job_postings.first_seen_at', '>=', now()->subDays(OutreachLimits::MAX_POSTING_AGE_DAYS))
                    ->orderByDesc('job_postings.first_seen_at')
                    ->limit(self::CANDIDATE_LIMIT)
                    ->get();

                $queued = false;

                foreach ($candidates as $posting) {
                    if ($queueApplication->handle($user, $posting, ApplicationOrigin::Auto) !== null) {
                        $queued = true;

                        break;
                    }
                }

                $queued ? $queuedCount++ : $skippedCount++;
            }
        }

        $this->info("Auto dispatch: {$usersConsidered} users considered, {$queuedCount} queued, {$skippedCount} skipped.");

        Log::info('outreach:dispatch-auto', [
            'users' => $usersConsidered,
            'queued' => $queuedCount,
            'skipped' => $skippedCount,
        ]);

        return self::SUCCESS;
    }
}
