<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OutreachStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case NoDomain = 'no_domain';
    case NotVerifiable = 'not_verifiable';
    case Verified = 'verified';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::NoDomain => 'No domain',
            self::NotVerifiable => 'Not verifiable',
            self::Verified => 'Verified',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::NoDomain => 'gray',
            self::NotVerifiable => 'danger',
            self::Verified => 'success',
        };
    }
}
