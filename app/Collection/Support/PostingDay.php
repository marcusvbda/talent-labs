<?php

namespace App\Collection\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class PostingDay
{
    /**
     * Start of today in the app timezone.
     */
    public static function todayStartsAt(): CarbonImmutable
    {
        return CarbonImmutable::now(config('app.timezone'))->startOfDay();
    }

    /**
     * Whether a run started today in the app timezone.
     */
    public static function isToday(?CarbonInterface $runStartedAt): bool
    {
        return $runStartedAt !== null && $runStartedAt->greaterThanOrEqualTo(self::todayStartsAt());
    }

    /**
     * Whole app-timezone days between the day the run started and today (0 = today).
     */
    public static function daysAgo(?CarbonInterface $runStartedAt): int
    {
        if ($runStartedAt === null) {
            return 0;
        }

        $startedOn = CarbonImmutable::instance($runStartedAt)->setTimezone(config('app.timezone'))->startOfDay();

        return max(0, (int) $startedOn->diffInDays(self::todayStartsAt()));
    }
}
