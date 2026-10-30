<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SendingPauseReason: string implements HasColor, HasLabel
{
    case Manual = 'manual';
    case ReauthorizationRequired = 'reauthorization_required';
    case RepeatedFailures = 'repeated_failures';
    case ProviderLimit = 'provider_limit';

    public function getLabel(): string
    {
        return match ($this) {
            self::Manual => 'Paused manually',
            self::ReauthorizationRequired => 'Email reauthorization',
            self::RepeatedFailures => 'Repeated failures',
            self::ProviderLimit => 'Provider sending limit',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Manual => 'gray',
            self::ReauthorizationRequired => 'warning',
            self::RepeatedFailures => 'danger',
            self::ProviderLimit => 'warning',
        };
    }
}
