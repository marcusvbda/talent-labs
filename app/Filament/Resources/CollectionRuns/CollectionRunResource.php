<?php

namespace App\Filament\Resources\CollectionRuns;

use App\Filament\Resources\CollectionRuns\Pages\ListCollectionRuns;
use App\Filament\Resources\CollectionRuns\Pages\ViewCollectionRun;
use App\Filament\Resources\CollectionRuns\RelationManagers\SourceRunsRelationManager;
use App\Filament\Resources\CollectionRuns\Schemas\CollectionRunInfolist;
use App\Filament\Resources\CollectionRuns\Tables\CollectionRunsTable;
use App\Models\CollectionRun;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CollectionRunResource extends Resource
{
    protected static ?string $model = CollectionRun::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'label';

    protected static string|UnitEnum|null $navigationGroup = 'Collection';

    protected static ?string $modelLabel = 'Run';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return CollectionRunInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CollectionRunsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            SourceRunsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCollectionRuns::route('/'),
            'view' => ViewCollectionRun::route('/{record}'),
        ];
    }
}
