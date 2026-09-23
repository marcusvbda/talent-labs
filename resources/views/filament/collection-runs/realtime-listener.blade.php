<x-filament-realtime-driver::listener
    :channel="'collection_run_'.$record->getKey()"
    event="CollectionRunUpdated"
    callback="$wire.$refresh()"
/>
