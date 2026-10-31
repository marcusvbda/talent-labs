<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SuppressionSource: string implements HasLabel
{
    case OptOut = 'opt_out';
    case Admin = 'admin';

    public function getLabel(): string
    {
        return match ($this) {
            self::OptOut => 'Opt-out',
            self::Admin => 'Admin',
        };
    }
}
