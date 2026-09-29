@php
    use App\Enums\ContactConfidence;
    use App\Enums\OutreachStatus;

    $global = $report['global'];
    $companies = $global['companies_by_outreach_status'];
    $contacts = $global['contacts_by_confidence'];
    $percent = fn (int $count, int $of): string => ($of > 0 ? round($count / $of * 100) : 0).'%';
    $client = $report['user'];
@endphp

<div class="grid gap-4">
    @if ($global['port25_warning'])
        <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="danger">
            <x-slot name="heading">Warning</x-slot>
            <span class="text-danger-600 dark:text-danger-400">
                Outbound port 25 is probably blocked ({{ round($global['mx_only_share'] * 100) }}% of contacts are mx_only).
            </span>
        </x-filament::section>
    @endif

    <x-filament::section heading="Companies by outreach status">
        <dl class="grid gap-4 sm:grid-cols-5">
            @foreach (OutreachStatus::cases() as $status)
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">{{ $status->getLabel() }}</dt>
                    <dd class="mt-1 font-medium">{{ $companies[$status->value] }}</dd>
                </div>
            @endforeach
            <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">Total</dt>
                <dd class="mt-1 font-medium">{{ $companies['total'] }}</dd>
            </div>
        </dl>
    </x-filament::section>

    <x-filament::section heading="Contacts by confidence">
        <dl class="grid gap-4 sm:grid-cols-4">
            @foreach (ContactConfidence::cases() as $confidence)
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">{{ $confidence->getLabel() }}</dt>
                    <dd class="mt-1 font-medium">{{ $contacts[$confidence->value] }} ({{ $percent($contacts[$confidence->value], $contacts['total']) }})</dd>
                </div>
            @endforeach
            <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">Total</dt>
                <dd class="mt-1 font-medium">{{ $contacts['total'] }} ({{ $percent($contacts['total'], $contacts['total']) }})</dd>
            </div>
        </dl>
        <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
            mx_only share: {{ round($global['mx_only_share'] * 100) }}%
        </p>
    </x-filament::section>

    @if ($client !== null)
        <x-filament::section :heading="'Client pool: '.$client['name'].' (#'.$client['id'].')'">
            <dl class="grid gap-4 sm:grid-cols-3 lg:grid-cols-6">
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">Plan</dt>
                    <dd class="mt-1 font-medium">{{ $client['plan'] }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">Daily limit</dt>
                    <dd class="mt-1 font-medium">{{ $client['daily_limit'] }}/day</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">Active languages</dt>
                    <dd class="mt-1 font-medium">{{ $client['active_languages'] === [] ? 'none' : implode(', ', $client['active_languages']) }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">Pool EN</dt>
                    <dd class="mt-1 font-medium">{{ $client['pool']['en'] }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">Pool PT</dt>
                    <dd class="mt-1 font-medium">{{ $client['pool']['pt'] }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">Applicable now</dt>
                    <dd class="mt-1 font-medium">{{ $client['pool']['total'] }}</dd>
                </div>
            </dl>
            <p class="mt-4 text-sm">Runway: {{ number_format($client['runway_days'], 1) }} days</p>
        </x-filament::section>
    @endif
</div>
