<?php

declare(strict_types=1);

namespace App\Filament\Resources\CatalogImports;

use App\Filament\Resources\CatalogImports\Pages\ListCatalogImports;
use App\Filament\Resources\CatalogImports\Pages\ViewCatalogImport;
use App\Filament\Resources\CatalogImports\Schemas\CatalogImportInfolist;
use App\Filament\Resources\CatalogImports\Tables\CatalogImportsTable;
use App\Models\CatalogImport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Read-only history of catalog imports. Runs are started from the Products
 * list, never created or edited here.
 */
class CatalogImportResource extends Resource
{
    protected static ?string $model = CatalogImport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static string|\UnitEnum|null $navigationGroup = 'Shop';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'original_filename';

    public static function getModelLabel(): string
    {
        return __('admin.catalog_import.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.catalog_import.plural_label');
    }

    public static function infolist(Schema $schema): Schema
    {
        return CatalogImportInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CatalogImportsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
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
            'index' => ListCatalogImports::route('/'),
            'view' => ViewCatalogImport::route('/{record}'),
        ];
    }
}
