<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ContactConfidence: string implements HasColor, HasLabel
{
    case SmtpVerified = 'smtp_verified';
    case CatchAll = 'catch_all';
    case MxOnly = 'mx_only';

    public function getLabel(): string
    {
        return match ($this) {
            self::SmtpVerified => 'SMTP verified',
            self::CatchAll => 'Catch-all',
            self::MxOnly => 'MX only',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::SmtpVerified => 'success',
            self::CatchAll => 'warning',
            self::MxOnly => 'info',
        };
    }
}
