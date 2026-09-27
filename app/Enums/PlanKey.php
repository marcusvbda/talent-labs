<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PlanKey: string implements HasLabel
{
    case Free = 'free';
    case Starter = 'starter';
    case Pro = 'pro';

    public function getLabel(): string
    {
        return match ($this) {
            self::Free => 'Free',
            self::Starter => 'Starter',
            self::Pro => 'Pro',
        };
    }
}
