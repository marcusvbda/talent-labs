<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ContactSource: string implements HasLabel
{
    case AliasProbe = 'alias_probe';
    case Posting = 'posting';
    case Named = 'named';

    public function getLabel(): string
    {
        return match ($this) {
            self::AliasProbe => 'Alias probe',
            self::Posting => 'Published in posting',
            self::Named => 'Named',
        };
    }
}
