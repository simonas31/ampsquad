<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductCategories\Pages;

use App\Filament\Concerns\EditsBilingualRecord;
use App\Filament\Resources\ProductCategories\ProductCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProductCategory extends EditRecord
{
    use EditsBilingualRecord;

    protected static string $resource = ProductCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->authorizationTooltip(),
        ];
    }
}
