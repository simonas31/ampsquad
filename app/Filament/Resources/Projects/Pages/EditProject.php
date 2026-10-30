<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Actions\RegenerateSlugAction;
use App\Filament\Concerns\EditsBilingualRecord;
use App\Filament\Resources\Projects\ProjectResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditProject extends EditRecord
{
    use EditsBilingualRecord;

    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RegenerateSlugAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
