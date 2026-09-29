<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PlanSource: string implements HasLabel
{
    case Manual = 'manual';
    case Stripe = 'stripe';

    public function getLabel(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Stripe => 'Stripe',
        };
    }
}
