<?php

namespace App\Filament\Resources\Sources\Schemas;

use App\Enums\SourceAdapter;
use App\Models\Source;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
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
                    ->maxLength(255)
                    ->disabled(),

                Select::make('adapter')
                    ->options(SourceAdapter::class)
                    ->required()
                    ->disabled()
                    ->live(),

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
                    ->visible(fn (callable $get): bool => ! (self::adapterFrom($get)?->requiresIdentifier() ?? true))
                    ->keyLabel('Key')
                    ->valueLabel('Value')
                    ->helperText(fn (callable $get): string => match (self::adapterFrom($get)) {
                        SourceAdapter::Remotive => 'Keys: category, search, limit',
                        SourceAdapter::Jobicy => 'Keys: count, geo, industry, tag',
                        SourceAdapter::RemoteOk, SourceAdapter::Arbeitnow => 'No settings used',
                        default => '',
                    }),

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
