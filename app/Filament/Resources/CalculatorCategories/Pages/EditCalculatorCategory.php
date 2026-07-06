<?php

declare(strict_types=1);

namespace App\Filament\Resources\CalculatorCategories\Pages;

use App\Filament\Resources\CalculatorCategories\CalculatorCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use LaraZeus\SpatieTranslatable\Resources\Pages\EditRecord\Concerns\Translatable;

class EditCalculatorCategory extends EditRecord
{
    use Translatable;

    protected static string $resource = CalculatorCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            DeleteAction::make(),
        ];
    }
}
