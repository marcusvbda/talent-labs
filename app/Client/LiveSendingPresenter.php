<?php

namespace App\Client;

use App\Enums\ApplicationStatus;
use App\Http\Resources\Client\ApplicationItemResource;
use App\Models\Application;
use App\Models\User;
use App\Outreach\Support\OutreachConfig;
use App\Outreach\Support\SendScheduler;
use App\Plans\PlanCatalog;
use Illuminate\Support\Carbon;

final class LiveSendingPresenter
{
    private const QUEUE_ITEMS = 3;

    public function __construct(
        private readonly SendScheduler $scheduler,
        private readonly PlanCatalog $plans,
    ) {}

    /**
     * Build the `LiveSending` contract (resources/js/types/contracts.ts) for the user.
     *
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        $limit = $this->plans->for($user)->dailyLimit;

        // The pre-checks (recipient, template, CV) record their stage while the row is still
        // queued; only the delivery itself flips it to sending. Both are "in progress".
        $current = $user->applications()
            ->where(fn ($in) => $in
                ->where('status', ApplicationStatus::Sending->value)
                ->orWhere(fn ($pre) => $pre
                    ->where('status', ApplicationStatus::Queued->value)
                    ->whereNotNull('stage')))
            ->with(['company', 'jobPosting.profile'])
            ->orderByRaw('status = ? desc', [ApplicationStatus::Sending->value])
            ->first();

        $waiting = fn () => $user->applications()
            ->where('status', ApplicationStatus::Queued->value)
            ->when($current !== null, fn ($query) => $query->whereKeyNot($current->id));

        $queuedCount = $waiting()->count();

        $queue = $waiting()
            ->with(['company', 'jobPosting.profile'])
            ->orderByRaw('scheduled_for IS NULL, scheduled_for ASC')
            ->orderBy('id')
            ->limit(self::QUEUE_ITEMS)
            ->get();

        // Every row belongs to the requesting user; reuse it instead of loading it per row.
        $current?->setRelation('user', $user);
        $queue->each(fn (Application $application) => $application->setRelation('user', $user));

        $nextSendAt = self::parse($waiting()->min('scheduled_for'));

        $pending = $current !== null || $queuedCount > 0;
        $state = $this->state($user, $limit, $current !== null, $queuedCount);

        $sentToday = $user->applications()
            ->where('status', ApplicationStatus::Sent->value)
            ->whereBetween('sent_at', [now()->startOfDay(), now()->endOfDay()])
            ->count();

        $waitStartedAt = $state === 'waiting' ? $this->waitStartedAt($user, $nextSendAt) : null;

        $estimatedFinishAt = $nextSendAt?->copy()->addSeconds(
            max(0, $queuedCount - 1) * OutreachConfig::averageIntervalSeconds(),
        );

        return [
            'state' => $state,
            'current' => $current === null ? null : (new ApplicationItemResource($current))->resolve(),
            'progress' => $pending ? ['index' => min($sentToday + 1, $limit), 'total' => $limit] : null,
            'queue' => ApplicationItemResource::collection($queue)->resolve(),
            'queuedCount' => $queuedCount,
            'nextSendAt' => $nextSendAt?->toIso8601String(),
            'waitStartedAt' => $waitStartedAt?->toIso8601String(),
            'estimatedFinishAt' => $estimatedFinishAt?->toIso8601String(),
            'spacing' => [
                'minSeconds' => OutreachConfig::intervalMinSeconds(),
                'maxSeconds' => OutreachConfig::intervalMaxSeconds(),
            ],
            'window' => OutreachConfig::windowEnabled() ? [
                'start' => OutreachConfig::windowStart(),
                'end' => OutreachConfig::windowEnd(),
                'weekdaysOnly' => OutreachConfig::weekdaysOnly(),
                'timezone' => $this->scheduler->timezone($user),
            ] : null,
        ];
    }

    /**
     * Where the countdown to the next send begins: the last finished send, but never earlier
     * than one maximum spacing before it. Queued slots are ignored: the last one lies in the future.
     */
    private function waitStartedAt(User $user, ?Carbon $nextSendAt): ?Carbon
    {
        if ($nextSendAt === null) {
            return null;
        }

        $lastSend = self::parse($user->applications()->max('sent_at'));
        $earliest = $nextSendAt->copy()->subSeconds(OutreachConfig::intervalMaxSeconds());

        return $lastSend === null || $lastSend->lessThan($earliest) ? $earliest : $lastSend;
    }

    /**
     * @return 'paused'|'limit_reached'|'outside_window'|'sending'|'waiting'|'idle'
     */
    private function state(User $user, int $limit, bool $sending, int $queuedCount): string
    {
        if ($user->isSendingPaused()) {
            return 'paused';
        }

        if (! $sending && $queuedCount === 0 && $this->remainingQuota($user, $limit) === 0) {
            return 'limit_reached';
        }

        if ($queuedCount > 0 && ! $this->scheduler->isInsideWindow($user)) {
            return 'outside_window';
        }

        if ($sending) {
            return 'sending';
        }

        return $queuedCount > 0 ? 'waiting' : 'idle';
    }

    /**
     * Same quota rule as `CanSendApplications`: applications counted towards today's limit.
     */
    private function remainingQuota(User $user, int $limit): int
    {
        return max(0, $limit - $user->applications()->countedToday()->count());
    }

    private static function parse(mixed $value): ?Carbon
    {
        return $value === null ? null : Carbon::parse($value, (string) config('app.timezone'));
    }
}
