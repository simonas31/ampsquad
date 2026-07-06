<?php

declare(strict_types=1);

namespace App\Filament\Resources\CalculatorCategories\Pages;

use App\Filament\Resources\CalculatorCategories\CalculatorCategoryResource;
use Filament\Resources\Pages\CreateRecord;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use LaraZeus\SpatieTranslatable\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateCalculatorCategory extends CreateRecord
{
    use Translatable;

    protected static string $resource = CalculatorCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
        ];
    }
}
