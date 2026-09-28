<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SendingPauseReason: string implements HasColor, HasLabel
{
    case Manual = 'manual';
    case ReauthorizationRequired = 'reauthorization_required';
    case RepeatedFailures = 'repeated_failures';

    public function getLabel(): string
    {
        return match ($this) {
            self::Manual => 'Paused manually',
            self::ReauthorizationRequired => 'Gmail reauthorization',
            self::RepeatedFailures => 'Repeated failures',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Manual => 'gray',
            self::ReauthorizationRequired => 'warning',
            self::RepeatedFailures => 'danger',
        };
    }
}
