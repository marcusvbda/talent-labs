<?php

namespace App\Filament\Resources\Sources;

use App\Filament\Resources\Sources\Pages\EditSource;
use App\Filament\Resources\Sources\Pages\ListSources;
use App\Filament\Resources\Sources\Schemas\SourceForm;
use App\Filament\Resources\Sources\Tables\SourcesTable;
use App\Models\Source;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SourceResource extends Resource
{
    protected static ?string $model = Source::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Collection';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return SourceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SourcesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSources::route('/'),
            'edit' => EditSource::route('/{record}/edit'),
        ];
    }
}
