<?php

namespace App\Filament\Resources\Users\Tables;

use App\Actions\Users\AdminGuard;
use App\Enums\ConnectedIntegrationStatus;
use App\Enums\UserStatus;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),

                IconColumn::make('is_admin')
                    ->label('Admin')
                    ->boolean(),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('gmailIntegration.status')
                    ->label('Gmail')
                    ->badge()
                    ->placeholder('Not connected')
                    ->default(null)
                    ->formatStateUsing(fn (mixed $state): string => $state instanceof ConnectedIntegrationStatus ? $state->getLabel() : 'Not connected')
                    ->color(fn (mixed $state): string => $state instanceof ConnectedIntegrationStatus ? $state->getColor() : 'gray'),

                TextColumn::make('sent_today_count')
                    ->label('Sent today')
                    ->numeric(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with('gmailIntegration')
                ->withCount(['applications as sent_today_count' => fn ($q) => $q->countedToday()]))
            ->filters([
                SelectFilter::make('status')
                    ->options(UserStatus::class),
            ])
            ->recordActions([
                Action::make('block')
                    ->label('Block')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (User $record): bool => $record->status === UserStatus::Active)
                    ->disabled(fn (User $record): bool => filled((new AdminGuard)->violation(auth()->user(), $record, 'block')))
                    ->tooltip(fn (User $record): ?string => (new AdminGuard)->violation(auth()->user(), $record, 'block'))
                    ->action(function (User $record): void {
                        $reason = (new AdminGuard)->violation(auth()->user(), $record, 'block');

                        if (filled($reason)) {
                            Notification::make()
                                ->title($reason)
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->update(['status' => UserStatus::Blocked]);

                        Notification::make()
                            ->title('User blocked.')
                            ->success()
                            ->send();
                    }),

                Action::make('unblock')
                    ->label('Unblock')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (User $record): bool => $record->status === UserStatus::Blocked)
                    ->action(function (User $record): void {
                        $record->update(['status' => UserStatus::Active]);

                        Notification::make()
                            ->title('User unblocked.')
                            ->success()
                            ->send();
                    }),

                EditAction::make(),

                DeleteAction::make()
                    ->disabled(fn (User $record): bool => filled((new AdminGuard)->violation(auth()->user(), $record, 'delete')))
                    ->tooltip(fn (User $record): ?string => (new AdminGuard)->violation(auth()->user(), $record, 'delete'))
                    ->before(function (User $record, DeleteAction $action): void {
                        $reason = (new AdminGuard)->violation(auth()->user(), $record, 'delete');

                        if (filled($reason)) {
                            Notification::make()
                                ->title($reason)
                                ->danger()
                                ->send();

                            $action->cancel();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
