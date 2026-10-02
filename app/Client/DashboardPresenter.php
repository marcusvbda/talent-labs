<?php

namespace App\Client;

use App\Enums\ApplicationStatus;
use App\Http\Resources\Client\ApplicationItemResource;
use App\Http\Resources\Client\JobCardResource;
use App\Models\Application;
use App\Models\User;
use App\Outreach\Queries\MatchingJobPostings;
use App\Plans\PlanCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

final class DashboardPresenter
{
    private const MATCH_ITEMS = 4;

    private const ACTIVITY_ITEMS = 5;

    private const LAST_DAYS = 5;

    public function __construct(private PlanCatalog $plans) {}

    /**
     * Build the `DashboardData` contract (resources/js/types/contracts.ts) for the user.
     *
     * @param  'today'|'week'|'month'|string  $period
     * @return array<string, mixed>
     */
    public function forUser(User $user, string $period): array
    {
        [$start, $end, $previousStart] = self::window($user, $period);
        $today = CarbonImmutable::now(ChartPresenter::timezone($user))->startOfDay();

        $sent = $user->applications()->where('status', ApplicationStatus::Sent->value);
        $pending = $user->applications()->whereIn('status', [ApplicationStatus::Queued->value, ApplicationStatus::Sending->value]);

        $firstSentAt = (clone $sent)->min('sent_at');
        // Only queued rows: a sending row's schedule is already due, so it isn't a "next" send.
        $nextSendAt = $user->applications()->where('status', ApplicationStatus::Queued->value)->min('scheduled_for');

        // Plans that can't choose jobs get counts only: no postings to pick from.
        $matches = $this->plans->for($user)->canChooseJobs()
            ? JobCardResource::collection(
                JobPoolQuery::forUser($user)
                    ->with(['profile', 'company', 'collectionRun'])
                    ->limit(self::MATCH_ITEMS)
                    ->get(),
            )->resolve()
            : [];

        $activity = $user->applications()
            ->with(['company', 'jobPosting.profile'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(self::ACTIVITY_ITEMS)
            ->get();

        // Every row belongs to the requesting user; reuse it instead of loading it per row.
        $activity->each(fn (Application $application) => $application->setRelation('user', $user));

        return [
            'period' => $period,
            'kpis' => [
                'collected' => [
                    'value' => self::collected($user, $start, '<=', $end),
                    'previous' => self::collected($user, $previousStart, '<', $start),
                ],
                'sent' => [
                    'value' => (clone $sent)->where('sent_at', '>=', $start)->where('sent_at', '<=', $end)->count(),
                    'previous' => (clone $sent)->where('sent_at', '>=', $previousStart)->where('sent_at', '<', $start)->count(),
                ],
                'totalSent' => (clone $sent)->count(),
                'firstSentAt' => self::iso($firstSentAt),
            ],
            'hero' => [
                'sentToday' => (clone $sent)->where('sent_at', '>=', self::toApp($today))->count(),
                'failedToday' => $user->applications()
                    ->where('status', ApplicationStatus::Failed->value)
                    ->where('updated_at', '>=', self::toApp($today))
                    ->count(),
                'queued' => (clone $pending)->count(),
                'nextSendAt' => self::iso($nextSendAt),
                'lastDays' => ChartPresenter::sentPerDay($user, $today->subDays(self::LAST_DAYS), self::LAST_DAYS),
            ],
            'matches' => [
                'total' => JobPoolQuery::forUser($user)->reorder()->count(),
                'newToday' => JobPoolQuery::forUser($user, ['today' => true])->reorder()->count(),
                'items' => $matches,
            ],
            'activity' => ApplicationItemResource::collection($activity)->resolve(),
        ];
    }

    /**
     * The current window [start, now] and the start of the previous one: the immediately
     * preceding window of equal length, [start - (now - start), start).
     * Computed in the user's timezone, returned in the app timezone the timestamps are stored in.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: CarbonImmutable}
     */
    public static function window(User $user, string $period): array
    {
        $now = CarbonImmutable::now(ChartPresenter::timezone($user));

        $start = match ($period) {
            'week' => $now->startOfWeek(),
            'month' => $now->startOfMonth(),
            default => $now->startOfDay(),
        };

        $previousStart = $start->subMicroseconds((int) $start->diffInMicroseconds($now));

        return [self::toApp($start), self::toApp($now), self::toApp($previousStart)];
    }

    /**
     * Pool postings first seen from `$from` up to `$to`, counted once per company.
     *
     * @param  '<'|'<='  $toOperator
     */
    private static function collected(User $user, CarbonImmutable $from, string $toOperator, CarbonImmutable $to): int
    {
        return MatchingJobPostings::forUser($user)
            ->where('job_postings.first_seen_at', '>=', $from)
            ->where('job_postings.first_seen_at', $toOperator, $to)
            ->distinct()
            ->count('job_postings.company_id');
    }

    private static function toApp(CarbonImmutable $moment): CarbonImmutable
    {
        return $moment->setTimezone((string) config('app.timezone'));
    }

    private static function iso(mixed $value): ?string
    {
        return $value === null ? null : Carbon::parse($value, (string) config('app.timezone'))->toIso8601String();
    }
}
