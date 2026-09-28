<?php

namespace App\Outreach\Support;

use App\Enums\ApplicationStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Throwable;

/**
 * Computes every send time: spaced by a random interval after the user's last
 * send activity (never a burst) and moved inside the user's sending window.
 */
class SendScheduler
{
    private const int WINDOW_JITTER_MAX_SECONDS = 120;

    /**
     * The user's IANA timezone when valid, else the app timezone.
     */
    public function timezone(User $user): string
    {
        $timezone = $user->timezone;

        if (is_string($timezone) && $timezone !== '') {
            try {
                new DateTimeZone($timezone);

                return $timezone;
            } catch (Throwable) {
                // Fall through to the app timezone.
            }
        }

        return $this->appTimezone();
    }

    /**
     * Latest send activity of the user: the last queued slot, the application
     * currently sending, or the last sent one. Null when there is none.
     */
    public function anchor(User $user): ?CarbonImmutable
    {
        $candidates = [
            $user->applications()->where('status', ApplicationStatus::Queued)->max('scheduled_for'),
            $user->applications()->where('status', ApplicationStatus::Sending)->max('updated_at'),
            $user->applications()->max('sent_at'),
        ];

        $latest = null;

        foreach ($candidates as $value) {
            $latest = $this->later($latest, $this->toCarbon($value));
        }

        return $latest;
    }

    /**
     * Next send time for the user, spaced after the anchor (or $after) and inside the window.
     */
    public function nextSlot(User $user, ?CarbonImmutable $after = null): CarbonImmutable
    {
        $anchor = $this->later($after, $this->anchor($user));
        $now = CarbonImmutable::now();

        if ($anchor === null) {
            $candidate = $now;
        } else {
            $spaced = $anchor->addSeconds(random_int(
                OutreachConfig::intervalMinSeconds(),
                OutreachConfig::intervalMaxSeconds(),
            ));

            $candidate = $spaced->greaterThan($now) ? $spaced : $now;
        }

        $slot = $this->intoWindow($user, $candidate);

        return CarbonImmutable::createFromTimestamp($slot->getTimestamp(), $slot->getTimezone());
    }

    /**
     * Whether $at (default now) falls inside the user's sending window.
     */
    public function isInsideWindow(User $user, ?CarbonImmutable $at = null): bool
    {
        if (! OutreachConfig::windowEnabled()) {
            return true;
        }

        $local = ($at ?? CarbonImmutable::now())->setTimezone($this->timezone($user));

        if (OutreachConfig::weekdaysOnly() && ! $local->isWeekday()) {
            return false;
        }

        $start = $local->setTimeFromTimeString(OutreachConfig::windowStart());
        $end = $local->setTimeFromTimeString(OutreachConfig::windowEnd());

        return $local->greaterThanOrEqualTo($start) && $local->lessThan($end);
    }

    /**
     * Next window start at or after $from in the user's timezone, plus a small
     * jitter, returned in the app timezone.
     */
    public function nextWindowStart(User $user, CarbonImmutable $from): CarbonImmutable
    {
        $local = $from->setTimezone($this->timezone($user));
        $start = $local->setTimeFromTimeString(OutreachConfig::windowStart());

        if ($local->greaterThanOrEqualTo($start)) {
            $start = $start->addDay()->setTimeFromTimeString(OutreachConfig::windowStart());
        }

        if (OutreachConfig::weekdaysOnly()) {
            while (! $start->isWeekday()) {
                $start = $start->addDay()->setTimeFromTimeString(OutreachConfig::windowStart());
            }
        }

        return $start
            ->addSeconds(random_int(0, self::WINDOW_JITTER_MAX_SECONDS))
            ->setTimezone($this->appTimezone());
    }

    /**
     * $at when it is inside the user's window, else the next window start.
     */
    public function intoWindow(User $user, CarbonImmutable $at): CarbonImmutable
    {
        if ($this->isInsideWindow($user, $at)) {
            return $at;
        }

        return $this->nextWindowStart($user, $at);
    }

    private function later(?CarbonImmutable $a, ?CarbonImmutable $b): ?CarbonImmutable
    {
        if ($a === null) {
            return $b;
        }

        if ($b === null) {
            return $a;
        }

        return $a->greaterThan($b) ? $a : $b;
    }

    private function toCarbon(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        return CarbonImmutable::parse($value, $this->appTimezone());
    }

    private function appTimezone(): string
    {
        return (string) config('app.timezone');
    }
}
