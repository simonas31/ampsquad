<?php

declare(strict_types=1);

namespace App\Filament\Resources\Capabilities\Pages;

use App\Filament\Resources\Capabilities\CapabilityResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use LaraZeus\SpatieTranslatable\Resources\Pages\ListRecords\Concerns\Translatable;

class ListCapabilities extends ListRecords
{
    use Translatable;

    protected static string $resource = CapabilityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            CreateAction::make(),
        ];
    }
}
