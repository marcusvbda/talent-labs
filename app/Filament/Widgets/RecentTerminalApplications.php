<?php

namespace App\Filament\Widgets;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentTerminalApplications extends TableWidget
{
    public function table(Table $table): Table
    {
        $ids = Application::query()
            ->whereIn('status', [ApplicationStatus::Sent, ApplicationStatus::Failed, ApplicationStatus::Ambiguous, ApplicationStatus::Cancelled])
            ->orderByDesc('updated_at')
            ->limit(50)
            ->pluck('id');

        return $table
            ->query(
                Application::query()
                    ->whereIn('id', $ids)
                    ->with(['user', 'company'])
                    ->orderByDesc('updated_at')
            )
            ->paginated(false)
            ->columns([
                TextColumn::make('user.name')
                    ->label('User'),

                TextColumn::make('company.name')
                    ->label('Company'),

                TextColumn::make('status')
                    ->badge(),

                TextColumn::make('stage')
                    ->badge()
                    ->placeholder('—'),

                TextColumn::make('sub_step')
                    ->label('Sub-step')
                    ->placeholder('—'),

                TextColumn::make('sent_at')
                    ->label('Sent at')
                    ->dateTime()
                    ->placeholder('—'),

                TextColumn::make('last_error')
                    ->label('Last error')
                    ->limit(60)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->placeholder('—'),
            ])
            ->socket(channel: 'applications', event: 'ApplicationsUpdated');
    }
}
