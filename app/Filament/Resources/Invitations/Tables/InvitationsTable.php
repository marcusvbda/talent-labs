<?php

namespace App\Filament\Resources\Invitations\Tables;

use App\Enums\PlanKey;
use App\Models\Invitation;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InvitationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['creator', 'usedBy']))
            ->columns([
                TextColumn::make('note')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('email')
                    ->searchable()
                    ->placeholder('Any email'),

                TextColumn::make('plan_key')
                    ->badge()
                    ->placeholder('Default'),

                TextColumn::make('status')
                    ->badge()
                    ->state(fn (Invitation $record): string => match (true) {
                        $record->isRevoked() => 'Revoked',
                        $record->isUsed() => "Used by {$record->usedBy?->name} on {$record->used_at?->toFormattedDateString()}",
                        $record->isExpired() => 'Expired',
                        default => 'Unused',
                    })
                    ->color(fn (Invitation $record): string => match (true) {
                        $record->isRevoked() => 'danger',
                        $record->isUsed() => 'gray',
                        $record->isExpired() => 'warning',
                        default => 'success',
                    }),

                TextColumn::make('creator.name')
                    ->label('Created by'),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('plan_key')
                    ->options(PlanKey::class),

                SelectFilter::make('status')
                    ->options([
                        'unused' => 'Unused',
                        'used' => 'Used',
                        'expired' => 'Expired',
                        'revoked' => 'Revoked',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'unused' => $query->whereNull('used_at')->whereNull('revoked_at')->where(function (Builder $query): void {
                                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                            }),
                            'used' => $query->whereNotNull('used_at'),
                            'expired' => $query->whereNull('used_at')->whereNull('revoked_at')->whereNotNull('expires_at')->where('expires_at', '<=', now()),
                            'revoked' => $query->whereNotNull('revoked_at'),
                            default => $query,
                        };
                    }),
            ])
            ->recordActions([
                Action::make('revoke')
                    ->label('Revoke')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Invitation $record): bool => $record->isUsable())
                    ->action(function (Invitation $record): void {
                        $record->update(['revoked_at' => now()]);

                        Notification::make()
                            ->title('Invitation revoked.')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->socket(channel: 'invitations', event: 'InvitationsUpdated');
    }
}
