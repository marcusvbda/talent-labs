<?php

namespace App\Outreach\Jobs;

use App\Enums\ConnectedIntegrationStatus;
use App\Enums\SendingPauseReason;
use App\Models\User;
use App\Outreach\Support\SendScheduler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Lifts a provider-limit pause at the next day's window. Only the pause it was
 * scheduled for is resumed: a manual resume, a newer pause or another reason
 * leaves the user as is.
 */
class ResumeSendingAfterProviderLimit implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $userId, public int $pausedAtTimestamp)
    {
        $this->onQueue('outreach');
    }

    public function handle(SendScheduler $scheduler): void
    {
        $user = User::query()->with('sendingIntegration')->find($this->userId);

        if ($user === null
            || $user->sending_pause_reason !== SendingPauseReason::ProviderLimit
            || $user->sending_paused_at?->getTimestamp() !== $this->pausedAtTimestamp
            || $user->sendingIntegration?->status !== ConnectedIntegrationStatus::Connected) {
            return;
        }

        $scheduler->resume($user);
    }
}
