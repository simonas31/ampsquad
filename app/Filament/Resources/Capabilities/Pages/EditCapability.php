<?php

declare(strict_types=1);

namespace App\Filament\Resources\Capabilities\Pages;

use App\Filament\Resources\Capabilities\CapabilityResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use LaraZeus\SpatieTranslatable\Resources\Pages\EditRecord\Concerns\Translatable;

class EditCapability extends EditRecord
{
    use Translatable;

    protected static string $resource = CapabilityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            DeleteAction::make(),
        ];
    }
}
