<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ConnectedIntegrationStatus: string implements HasColor, HasLabel
{
    case Connected = 'connected';
    case ReauthorizationRequired = 'reauthorization_required';
    case Disconnected = 'disconnected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Connected => 'Connected',
            self::ReauthorizationRequired => 'Reauthorization required',
            self::Disconnected => 'Disconnected',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Connected => 'success',
            self::ReauthorizationRequired => 'warning',
            self::Disconnected => 'gray',
        };
    }
}
