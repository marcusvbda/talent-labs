<?php

namespace App\Filament\Widgets;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class InFlightApplications extends TableWidget
{
    public function table(Table $table): Table
    {
        return $table
            ->query(
                Application::query()
                    ->whereIn('status', [ApplicationStatus::Queued, ApplicationStatus::Sending])
                    ->with(['user', 'company'])
            )
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->orderByRaw('scheduled_for IS NULL, scheduled_for ASC'))
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

                TextColumn::make('scheduled_for')
                    ->label('Scheduled for')
                    ->dateTime(),

                TextColumn::make('attempts'),
            ])
            ->socket(channel: 'applications', event: 'ApplicationsUpdated');
    }
}
