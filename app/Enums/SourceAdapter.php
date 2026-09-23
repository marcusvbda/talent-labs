<?php

namespace App\Enums;

use App\Collection\Adapters\AshbyAdapter;
use App\Collection\Adapters\GreenhouseAdapter;
use App\Collection\Adapters\LeverAdapter;
use App\Collection\Adapters\RemotiveAdapter;
use App\Collection\Contracts\JobSourceAdapter;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SourceAdapter: string implements HasColor, HasLabel
{
    case Greenhouse = 'greenhouse';
    case Lever = 'lever';
    case Ashby = 'ashby';
    case Remotive = 'remotive';

    public function getLabel(): string
    {
        return match ($this) {
            self::Greenhouse => 'Greenhouse',
            self::Lever => 'Lever',
            self::Ashby => 'Ashby',
            self::Remotive => 'Remotive',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Greenhouse => 'success',
            self::Lever => 'info',
            self::Ashby => 'warning',
            self::Remotive => 'gray',
        };
    }

    public function requiresIdentifier(): bool
    {
        return $this !== self::Remotive;
    }

    public function sourceLabel(): string
    {
        return match ($this) {
            self::Remotive => 'via Remotive',
            default => $this->getLabel(),
        };
    }

    public function adapter(): JobSourceAdapter
    {
        return app(match ($this) {
            self::Greenhouse => GreenhouseAdapter::class,
            self::Lever => LeverAdapter::class,
            self::Ashby => AshbyAdapter::class,
            self::Remotive => RemotiveAdapter::class,
        });
    }
}
