<?php

namespace App\Console\Commands;

use App\Actions\Collection\StartCollectionRun;
use App\Exceptions\CollectionRunException;
use App\Models\CollectionSchedule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CollectionTick extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'collection:tick';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start a collection run when the current minute is a configured schedule slot';

    public function handle(): int
    {
        CollectionSchedule::forgetCurrent();
        $schedule = CollectionSchedule::current();

        if (! $schedule->enabled) {
            return self::SUCCESS;
        }

        $now = now($schedule->timezone);
        $slot = $now->format('H:i');

        if (! in_array($slot, $schedule->times, true)) {
            return self::SUCCESS;
        }

        $slotKey = $now->format('Y-m-d').' '.$slot;

        if ($slotKey === $schedule->last_slot_key) {
            return self::SUCCESS;
        }

        // Atomic claim: only one tick may own this slot, even with concurrent schedulers.
        $claimed = CollectionSchedule::query()
            ->whereKey($schedule->id)
            ->whereRaw('last_slot_key is distinct from ?', [$slotKey])
            ->update(['last_slot_key' => $slotKey, 'updated_at' => now()]);

        if ($claimed === 0) {
            return self::SUCCESS;
        }

        try {
            $run = app(StartCollectionRun::class)->handle(null);
            $result = "Started run #{$run->id}";
        } catch (CollectionRunException $e) {
            $result = 'Skipped: '.$e->getMessage();
        }

        $schedule->forceFill([
            'last_slot_key' => $slotKey,
            'last_result' => Str::limit($result, 200, ''),
            'last_dispatched_at' => now(),
        ])->save();

        $this->info($result);
        Log::info('collection:tick', ['slot' => $slotKey, 'result' => $result]);

        return self::SUCCESS;
    }
}
