<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SourceContactKind: string implements HasLabel
{
    case Email = 'email';
    case Founder = 'founder';
    case HiringManager = 'hiring_manager';

    public function getLabel(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::Founder => 'Founder',
            self::HiringManager => 'Hiring manager',
        };
    }
}
