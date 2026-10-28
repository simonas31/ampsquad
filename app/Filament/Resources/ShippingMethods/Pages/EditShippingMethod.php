<?php

declare(strict_types=1);

namespace App\Filament\Resources\ShippingMethods\Pages;

use App\Filament\Resources\ShippingMethods\ShippingMethodResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use LaraZeus\SpatieTranslatable\Resources\Pages\EditRecord\Concerns\Translatable;

class EditShippingMethod extends EditRecord
{
    use Translatable;

    protected static string $resource = ShippingMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            DeleteAction::make(),
        ];
    }
}
