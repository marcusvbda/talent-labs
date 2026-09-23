<?php

namespace App\Filament\Resources\Sources\Schemas;

use App\Enums\SourceAdapter;
use App\Models\Source;
use Closure;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;

class SourceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->string()
                    ->maxLength(255),

                Select::make('adapter')
                    ->options(SourceAdapter::class)
                    ->required()
                    ->live()
                    // Remotive's identifier is always null, so the identifier
                    // field's unique() rule below never applies to it — the
                    // DB's nullsNotDistinct() unique index still rejects a
                    // second Remotive row, so this catches that case with a
                    // friendly message instead of a 500.
                    ->rule(fn (?Model $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                        if ($value !== SourceAdapter::Remotive->value) {
                            return;
                        }

                        $exists = Source::query()
                            ->where('adapter', SourceAdapter::Remotive->value)
                            ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                            ->exists();

                        if ($exists) {
                            $fail('A Remotive source already exists.');
                        }
                    }),

                TextInput::make('identifier')
                    ->required(fn (callable $get): bool => self::adapterFrom($get)?->requiresIdentifier() ?? true)
                    ->visible(fn (callable $get): bool => self::adapterFrom($get)?->requiresIdentifier() ?? true)
                    ->dehydrateStateUsing(fn (callable $get, ?string $state): ?string => (self::adapterFrom($get)?->requiresIdentifier() ?? true) ? $state : null)
                    ->maxLength(255)
                    ->unique(
                        table: Source::class,
                        column: 'identifier',
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, callable $get): Unique => $rule->where('adapter', self::adapterFrom($get)?->value),
                    ),

                KeyValue::make('settings')
                    ->visible(fn (callable $get): bool => self::adapterFrom($get) === SourceAdapter::Remotive)
                    ->keyLabel('Key')
                    ->valueLabel('Value')
                    ->helperText('Keys: category, search, limit'),

                TextInput::make('interval_minutes')
                    ->label('Interval (minutes)')
                    ->numeric()
                    ->minValue(1)
                    ->default(60)
                    ->required()
                    ->helperText('Stored for future automatic collection; not used yet'),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }

    private static function adapterFrom(callable $get): ?SourceAdapter
    {
        $value = $get('adapter');

        if ($value instanceof SourceAdapter) {
            return $value;
        }

        return SourceAdapter::tryFrom((string) $value);
    }
}
