<?php

declare(strict_types=1);

namespace App\Filament\Resources\ShippingMethods\Pages;

use App\Filament\Resources\ShippingMethods\ShippingMethodResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use LaraZeus\SpatieTranslatable\Resources\Pages\ListRecords\Concerns\Translatable;

class ListShippingMethods extends ListRecords
{
    use Translatable;

    protected static string $resource = ShippingMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            CreateAction::make(),
        ];
    }
}
