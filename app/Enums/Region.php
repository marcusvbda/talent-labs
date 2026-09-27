<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Region: string implements HasLabel
{
    case Br = 'br';
    case Eu = 'eu';
    case Row = 'row';

    public function getLabel(): string
    {
        return match ($this) {
            self::Br => 'Brazil',
            self::Eu => 'Europe',
            self::Row => 'Rest of world',
        };
    }

    public function currency(): string
    {
        return (string) config("talent.regions.{$this->value}.currency");
    }
}
