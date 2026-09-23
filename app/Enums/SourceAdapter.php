<?php

namespace App\Enums;

use App\Collection\Adapters\ArbeitnowAdapter;
use App\Collection\Adapters\AshbyAdapter;
use App\Collection\Adapters\GreenhouseAdapter;
use App\Collection\Adapters\JobicyAdapter;
use App\Collection\Adapters\LeverAdapter;
use App\Collection\Adapters\RemoteOkAdapter;
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
    case RemoteOk = 'remote_ok';
    case Arbeitnow = 'arbeitnow';
    case Jobicy = 'jobicy';

    public function getLabel(): string
    {
        return match ($this) {
            self::Greenhouse => 'Greenhouse',
            self::Lever => 'Lever',
            self::Ashby => 'Ashby',
            self::Remotive => 'Remotive',
            self::RemoteOk => 'RemoteOK',
            self::Arbeitnow => 'Arbeitnow',
            self::Jobicy => 'Jobicy',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Greenhouse => 'success',
            self::Lever => 'info',
            self::Ashby => 'warning',
            self::Remotive, self::RemoteOk, self::Arbeitnow, self::Jobicy => 'gray',
        };
    }

    public function requiresIdentifier(): bool
    {
        return match ($this) {
            self::Greenhouse, self::Lever, self::Ashby => true,
            self::Remotive, self::RemoteOk, self::Arbeitnow, self::Jobicy => false,
        };
    }

    public function sourceLabel(): string
    {
        return match ($this) {
            self::Remotive => 'via Remotive',
            self::RemoteOk => 'via RemoteOK',
            self::Arbeitnow => 'via Arbeitnow',
            self::Jobicy => 'via Jobicy',
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
            self::RemoteOk => RemoteOkAdapter::class,
            self::Arbeitnow => ArbeitnowAdapter::class,
            self::Jobicy => JobicyAdapter::class,
        });
    }
}
