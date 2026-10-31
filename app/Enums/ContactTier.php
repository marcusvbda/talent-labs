<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ContactTier: string implements HasColor, HasLabel
{
    case A = 'a';
    case B = 'b';
    case C = 'c';

    public function getLabel(): string
    {
        return match ($this) {
            self::A => 'A — Published',
            self::B => 'B — Named',
            self::C => 'C — Role alias',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::A => 'success',
            self::B => 'info',
            self::C => 'gray',
        };
    }
}
