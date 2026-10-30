<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Actions\RegenerateSlugAction;
use App\Filament\Concerns\EditsBilingualRecord;
use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    use EditsBilingualRecord;

    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RegenerateSlugAction::make(),
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
