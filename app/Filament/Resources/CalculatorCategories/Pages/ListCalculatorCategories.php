<?php

declare(strict_types=1);

namespace App\Filament\Resources\CalculatorCategories\Pages;

use App\Filament\Resources\CalculatorCategories\CalculatorCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use LaraZeus\SpatieTranslatable\Resources\Pages\ListRecords\Concerns\Translatable;

class ListCalculatorCategories extends ListRecords
{
    use Translatable;

    protected static string $resource = CalculatorCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            CreateAction::make(),
        ];
    }
}
