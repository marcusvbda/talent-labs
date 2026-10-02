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
     * Start of the oldest day (app timezone) still inside the client pool window
     * (talent.collection.window_days, today included, at least one day).
     */
    public static function windowStartsAt(): CarbonImmutable
    {
        return self::todayStartsAt()->subDays(max(1, (int) config('talent.collection.window_days')) - 1);
    }

    /**
     * Whether a run started today in the app timezone.
     */
    public static function isToday(?CarbonInterface $runStartedAt): bool
    {
        return $runStartedAt !== null && $runStartedAt->greaterThanOrEqualTo(self::todayStartsAt());
    }
}
