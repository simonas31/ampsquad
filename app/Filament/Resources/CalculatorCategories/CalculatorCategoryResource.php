<?php

declare(strict_types=1);

namespace App\Filament\Resources\CalculatorCategories;

use App\Filament\Resources\CalculatorCategories\Pages\CreateCalculatorCategory;
use App\Filament\Resources\CalculatorCategories\Pages\EditCalculatorCategory;
use App\Filament\Resources\CalculatorCategories\Pages\ListCalculatorCategories;
use App\Filament\Resources\CalculatorCategories\RelationManagers\OptionsRelationManager;
use App\Filament\Resources\CalculatorCategories\Schemas\CalculatorCategoryForm;
use App\Filament\Resources\CalculatorCategories\Tables\CalculatorCategoriesTable;
use App\Models\CalculatorCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;

class CalculatorCategoryResource extends Resource
{
    use Translatable;

    protected static ?string $model = CalculatorCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('admin.calculator_categories.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.calculator_categories.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return CalculatorCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CalculatorCategoriesTable::configure($table);
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
            'index' => ListCalculatorCategories::route('/'),
            'create' => CreateCalculatorCategory::route('/create'),
            'edit' => EditCalculatorCategory::route('/{record}/edit'),
        ];
    }
}
