<x-filament-realtime-driver::listener
    channel="companies"
    event="CompanyUpdated"
    callback="$wire.$refresh()"
/>
