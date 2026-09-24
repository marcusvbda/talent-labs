<x-filament-panels::page>
    {{ $this->table }}

    <x-filament-realtime-driver::listener channel="applications" event="ApplicationsUpdated" callback="$wire.$refresh()" />
</x-filament-panels::page>
