<?php

namespace App\Outreach\Support;

use InvalidArgumentException;

/**
 * Typed readers for the `talent.outreach` sending knobs, plus boot-time validation.
 */
final class OutreachConfig
{
    private function __construct() {}

    public static function intervalMinSeconds(): int
    {
        return (int) config('talent.outreach.interval_min_seconds');
    }

    public static function intervalMaxSeconds(): int
    {
        return (int) config('talent.outreach.interval_max_seconds');
    }

    public static function averageIntervalSeconds(): int
    {
        return intdiv(self::intervalMinSeconds() + self::intervalMaxSeconds(), 2);
    }

    public static function stepDelayMs(): int
    {
        return (int) config('talent.outreach.step_delay_ms');
    }

    public static function windowEnabled(): bool
    {
        return (bool) config('talent.outreach.window.enabled');
    }

    public static function windowStart(): string
    {
        return (string) config('talent.outreach.window.start');
    }

    public static function windowEnd(): string
    {
        return (string) config('talent.outreach.window.end');
    }

    public static function weekdaysOnly(): bool
    {
        return (bool) config('talent.outreach.window.weekdays_only');
    }

    public static function autoPauseAfterFailures(): int
    {
        return (int) config('talent.outreach.auto_pause_after_failures');
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function validate(): void
    {
        $min = self::intervalMinSeconds();
        $max = self::intervalMaxSeconds();
        $stepDelay = self::stepDelayMs();

        if ($min < 5) {
            throw new InvalidArgumentException(
                "OUTREACH_SEND_INTERVAL_MIN_SECONDS must be at least 5 (got {$min}).",
            );
        }

        if ($min > $max) {
            throw new InvalidArgumentException(
                "OUTREACH_SEND_INTERVAL_MIN_SECONDS ({$min}) must not be greater than OUTREACH_SEND_INTERVAL_MAX_SECONDS ({$max}).",
            );
        }

        if ($stepDelay > 3000) {
            throw new InvalidArgumentException(
                "OUTREACH_STEP_DELAY_MS must be at most 3000 (got {$stepDelay}).",
            );
        }
    }
}
