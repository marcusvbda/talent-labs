<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RemoteMode: string implements HasLabel
{
    case RemoteOnly = 'remote_only';
    case RemoteOrLocations = 'remote_or_locations';
    case LocationsOnly = 'locations_only';

    public function getLabel(): string
    {
        return match ($this) {
            self::RemoteOnly => 'Remote only',
            self::RemoteOrLocations => 'Remote or these locations',
            self::LocationsOnly => 'Only these locations',
        };
    }
}
