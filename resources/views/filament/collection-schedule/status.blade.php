@php
    \App\Models\CollectionSchedule::forgetCurrent();
    $schedule = \App\Models\CollectionSchedule::current();
    $tz = $schedule->timezone;
    $next = $schedule->nextRunAt();

    if ($next === null) {
        $nextLabel = '— (paused)';
    } else {
        $today = \Carbon\CarbonImmutable::now($tz)->toDateString();
        $nextLabel = ($next->toDateString() === $today ? 'today' : 'tomorrow').' '.$next->format('H:i').' ('.$tz.')';
    }

    $lastLabel = 'No automatic run yet';
    if ($schedule->last_result !== null) {
        $lastLabel = $schedule->last_result;
        if ($schedule->last_dispatched_at !== null) {
            $lastLabel .= ' · '.$schedule->last_dispatched_at->setTimezone($tz)->format('j M Y H:i');
        }
    }

    $run = \App\Models\CollectionRun::query()->latest('id')->first();
@endphp

<dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <div>
        <dt class="text-sm text-gray-500 dark:text-gray-400">State</dt>
        <dd class="mt-1">
            <x-filament::badge :color="$schedule->enabled ? 'success' : 'gray'">
                Automatic collection is {{ $schedule->enabled ? 'on' : 'off' }}
            </x-filament::badge>
        </dd>
    </div>
    <div>
        <dt class="text-sm text-gray-500 dark:text-gray-400">Next run</dt>
        <dd class="mt-1 text-sm">{{ $nextLabel }}</dd>
    </div>
    <div>
        <dt class="text-sm text-gray-500 dark:text-gray-400">Last result</dt>
        <dd class="mt-1 text-sm">{{ $lastLabel }}</dd>
    </div>
    <div>
        <dt class="text-sm text-gray-500 dark:text-gray-400">Latest run</dt>
        <dd class="mt-1 text-sm">
            @if ($run)
                <a
                    href="{{ \App\Filament\Resources\CollectionRuns\CollectionRunResource::getUrl('view', ['record' => $run]) }}"
                    class="text-primary-600 hover:underline dark:text-primary-400"
                >{{ $run->label }}</a>
                <x-filament::badge :color="$run->status->getColor()" class="ml-1 inline-flex">
                    {{ $run->status->getLabel() }}
                </x-filament::badge>
            @else
                —
            @endif
        </dd>
    </div>
</dl>
