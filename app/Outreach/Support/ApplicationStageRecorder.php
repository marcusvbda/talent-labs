<?php

namespace App\Outreach\Support;

use App\Enums\SendStage;
use App\Enums\SendSubStep;
use App\Events\Client\ApplicationProgressed;
use App\Events\Client\Concerns\DispatchesClientEvent;
use App\Models\Application;
use App\Models\Concerns\BroadcastsRealtime;
use Illuminate\Support\Facades\DB;

/**
 * Persists and broadcasts the send pipeline's stage and sub-step of an application.
 *
 * Writes go through the query builder: they never touch `updated_at` and fire no
 * model events, so the client and admin events are dispatched explicitly.
 */
final class ApplicationStageRecorder
{
    use BroadcastsRealtime;
    use DispatchesClientEvent;

    public function record(Application $application, SendStage $stage, ?SendSubStep $subStep): void
    {
        $log = $application->stage_log ?? [];

        if ($application->stage !== $stage) {
            $entry = ['stage' => $stage->value, 'at' => now()->toIso8601String()];

            DB::statement(
                'update applications set stage = ?, sub_step = ?, stage_log = stage_log || ?::jsonb where id = ?',
                [$stage->value, $subStep?->value, json_encode([$entry], JSON_THROW_ON_ERROR), $application->id],
            );

            $log[] = $entry;
        } else {
            Application::query()->whereKey($application->id)->update([
                'stage' => $stage->value,
                'sub_step' => $subStep?->value,
            ]);
        }

        $this->sync($application, $stage->value, $subStep?->value, $log);
        $this->broadcast($application);
    }

    public function reset(Application $application): void
    {
        Application::query()->whereKey($application->id)->update([
            'stage' => null,
            'sub_step' => null,
        ]);

        $this->sync($application, null, null, $application->stage_log ?? []);
        $this->broadcast($application);
    }

    public function pace(): void
    {
        $delay = OutreachConfig::stepDelayMs();

        if ($delay > 0) {
            usleep($delay * 1000);
        }
    }

    /**
     * Mirrors the write on the in-memory model so a later save() does not overwrite it.
     *
     * @param  array<int, mixed>  $log
     */
    private function sync(Application $application, ?string $stage, ?string $subStep, array $log): void
    {
        $application->setRawAttributes([
            ...$application->getAttributes(),
            'stage' => $stage,
            'sub_step' => $subStep,
            'stage_log' => json_encode(array_values($log), JSON_THROW_ON_ERROR),
        ]);

        $application->syncOriginalAttributes(['stage', 'sub_step', 'stage_log']);
    }

    private function broadcast(Application $application): void
    {
        self::dispatchClientEvent(new ApplicationProgressed($application->id, $application->user_id));
        self::broadcastRealtime('applications', 'ApplicationsUpdated', ['id' => $application->id]);
    }
}
