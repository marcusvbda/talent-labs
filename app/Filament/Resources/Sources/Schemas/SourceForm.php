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
    /**
     * Supported `settings` keys per adapter value; adapters not listed use no settings.
     */
    private const SETTINGS_HINTS = [
        'remotive' => 'Keys: category, search, limit.',
        'remote_ok' => 'Key: tags (comma list, max 10, one request per tag). Empty = the whole feed.',
        'arbeitnow' => 'Keys: pages (1-5), remote ("true" = remote jobs only). Empty = first page only.',
        'jobicy' => 'Keys: industries (comma list, max 10, one request per industry), count (max 100), geo, tag. Empty = default feed.',
        'himalayas' => 'Keys: queries (comma list), pages, country, worldwide, seniority.',
        'we_work_remotely' => 'Key: categories (comma list of feed slugs).',
        'working_nomads' => 'Key: categories (comma list, matched against the category name).',
    ];

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
                    ->helperText(fn (callable $get): string => self::SETTINGS_HINTS[self::adapterFrom($get)?->value] ?? 'No settings used.'),

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
