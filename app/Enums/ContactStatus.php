<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ContactStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case NoDomain = 'no_domain';
    case Found = 'found';
    case NotFound = 'not_found';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::NoDomain => 'No domain',
            self::Found => 'Found',
            self::NotFound => 'Not found',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::NoDomain => 'gray',
            self::Found => 'success',
            self::NotFound => 'danger',
        };
    }
}
