<?php

declare(strict_types=1);

namespace App\Filament\Resources\Capabilities;

use App\Filament\Resources\Capabilities\Pages\CreateCapability;
use App\Filament\Resources\Capabilities\Pages\EditCapability;
use App\Filament\Resources\Capabilities\Pages\ListCapabilities;
use App\Filament\Resources\Capabilities\RelationManagers\OptionsRelationManager;
use App\Filament\Resources\Capabilities\Schemas\CapabilityForm;
use App\Filament\Resources\Capabilities\Tables\CapabilitiesTable;
use App\Models\Capability;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;

class CapabilityResource extends Resource
{
    use Translatable;

    protected static ?string $model = Capability::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('admin.capabilities.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.capabilities.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return CapabilityForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CapabilitiesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            OptionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCapabilities::route('/'),
            'create' => CreateCapability::route('/create'),
            'edit' => EditCapability::route('/{record}/edit'),
        ];
    }
}
