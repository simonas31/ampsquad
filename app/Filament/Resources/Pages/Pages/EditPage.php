<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Actions\RegenerateSlugAction;
use App\Filament\Concerns\EditsBilingualRecord;
use App\Filament\Resources\Pages\PageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPage extends EditRecord
{
    use EditsBilingualRecord;

    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RegenerateSlugAction::make(),
            DeleteAction::make(),
        ];
    }
}
