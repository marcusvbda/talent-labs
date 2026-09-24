<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ApplicationOrigin: string implements HasColor, HasLabel
{
    case Auto = 'auto';
    case Manual = 'manual';

    public function getLabel(): string
    {
        return match ($this) {
            self::Auto => 'Automatic',
            self::Manual => 'Manual',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Auto => 'info',
            self::Manual => 'gray',
        };
    }
}
