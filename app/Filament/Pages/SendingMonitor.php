<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\InFlightApplications;
use App\Filament\Widgets\RecentTerminalApplications;
use Filament\Pages\Page;
use UnitEnum;

class SendingMonitor extends Page
{
    protected static ?string $slug = 'sending-monitor';

    protected static ?string $title = 'Sending monitor';

    protected static ?string $navigationLabel = 'Sending monitor';

    protected static string|UnitEnum|null $navigationGroup = 'Outreach';

    /**
     * @return array<class-string>
     */
    protected function getFooterWidgets(): array
    {
        return [
            InFlightApplications::class,
            RecentTerminalApplications::class,
        ];
    }
}
