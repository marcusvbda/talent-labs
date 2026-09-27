<?php

namespace App\Client;

use App\Enums\ApplicationStatus;
use App\Models\User;
use App\Plans\PlanCatalog;
use Carbon\CarbonImmutable;

final class ChartPresenter
{
    public function __construct(private PlanCatalog $plans) {}

    /**
     * Build the `ChartData` contract (resources/js/types/contracts.ts) for the user.
     *
     * @param  '14d'|'30d'|string  $range
     * @return array{range: string, days: list<array{date: string, count: int}>, averagePerActiveDay: float|int, limit: int}
     */
    public function forUser(User $user, string $range): array
    {
        $length = $range === '30d' ? 30 : 14;

        $today = CarbonImmutable::now(self::timezone($user))->startOfDay();
        $days = self::sentPerDay($user, $today->subDays($length - 1), $length);

        $total = array_sum(array_column($days, 'count'));
        $activeDays = count(array_filter($days, fn (array $day): bool => $day['count'] > 0));

        return [
            'range' => $range,
            'days' => $days,
            'averagePerActiveDay' => $activeDays === 0 ? 0 : round($total / $activeDays, 1),
            'limit' => $this->plans->for($user)->dailyLimit,
        ];
    }

    /**
     * The user's timezone, falling back to the app timezone.
     */
    public static function timezone(User $user): string
    {
        return $user->timezone ?: (string) config('app.timezone');
    }

    /**
     * Sent applications per calendar day in the user's timezone, oldest first and zero-filled,
     * for `$length` days starting at `$firstDay` (a start of day in the user's timezone).
     *
     * @return list<array{date: string, count: int}>
     */
    public static function sentPerDay(User $user, CarbonImmutable $firstDay, int $length): array
    {
        $appTimezone = (string) config('app.timezone');
        $timezone = self::timezone($user);

        // Timestamps are stored without a zone, in the app timezone.
        $counts = $user->applications()
            ->toBase()
            ->selectRaw("to_char((sent_at at time zone ?) at time zone ?, 'YYYY-MM-DD') as day, count(*) as total", [$appTimezone, $timezone])
            ->where('status', ApplicationStatus::Sent->value)
            ->where('sent_at', '>=', $firstDay->setTimezone($appTimezone))
            ->where('sent_at', '<', $firstDay->addDays($length)->setTimezone($appTimezone))
            ->groupBy('day')
            ->pluck('total', 'day');

        $days = [];

        for ($offset = 0; $offset < $length; $offset++) {
            $date = $firstDay->addDays($offset)->format('Y-m-d');
            $days[] = ['date' => $date, 'count' => (int) ($counts[$date] ?? 0)];
        }

        return $days;
    }
}
