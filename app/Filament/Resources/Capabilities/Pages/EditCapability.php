<?php

declare(strict_types=1);

namespace App\Filament\Resources\Capabilities\Pages;

use App\Filament\Concerns\EditsBilingualRecord;
use App\Filament\Resources\Capabilities\CapabilityResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCapability extends EditRecord
{
    use EditsBilingualRecord;

    protected static string $resource = CapabilityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
