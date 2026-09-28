<?php

namespace App\Outreach\Support;

use App\Enums\ApplicationStatus;
use App\Enums\SendingPauseReason;
use App\Events\Client\SendingUpdated;
use App\Models\Application;
use App\Models\User;
use App\Outreach\Jobs\SendApplicationEmail;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\DB;
use Marcusvbda\FilamentRealtimeDriver\RealtimeEvent;
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

    /**
     * Pauses the user's sending. Queued rows park at their next job start; an
     * in-flight send finishes normally. False when already paused.
     */
    public function pause(User $user, SendingPauseReason $reason): bool
    {
        $paused = DB::transaction(function () use ($user, $reason): bool {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($locked->isSendingPaused()) {
                return false;
            }

            $locked->forceFill([
                'sending_paused_at' => now(),
                'sending_pause_reason' => $reason,
            ])->save();

            return true;
        });

        if ($paused) {
            $user->refresh();
            SendingUpdated::broadcastFor($user->id);
        }

        return $paused;
    }

    /**
     * Clears the pause and re-slots every queued application in queue order.
     * Jobs of the previous slots become stale and exit on start.
     */
    public function resume(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $locked->forceFill([
                'sending_paused_at' => null,
                'sending_pause_reason' => null,
            ])->save();

            // Cleared first so the anchor ignores the old slots.
            Application::query()
                ->whereBelongsTo($locked)
                ->where('status', ApplicationStatus::Queued)
                ->update(['scheduled_for' => null]);

            $rows = Application::query()
                ->whereBelongsTo($locked)
                ->where('status', ApplicationStatus::Queued)
                ->orderBy('queued_at')
                ->orderBy('id')
                ->get(['id']);

            $previousSlot = null;

            foreach ($rows as $row) {
                $slot = $this->nextSlot($locked, $previousSlot);
                Application::query()->whereKey($row->id)->update(['scheduled_for' => $slot]);
                SendApplicationEmail::dispatch($row->id, $slot)->delay($slot)->afterCommit();
                $previousSlot = $slot;
            }
        });

        $user->refresh();
        SendingUpdated::broadcastFor($user->id);

        // Query-builder writes skip model events: refresh the admin table explicitly.
        try {
            RealtimeEvent::dispatch('applications', 'ApplicationsUpdated', ['userId' => $user->id]);
        } catch (BroadcastException $e) {
            report($e);
        }
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
