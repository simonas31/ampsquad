<?php

declare(strict_types=1);

namespace App\Filament\Resources\Videos\Pages;

use App\Filament\Concerns\CreatesBilingualRecord;
use App\Filament\Resources\Videos\VideoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVideo extends CreateRecord
{
    use CreatesBilingualRecord;

    protected static string $resource = VideoResource::class;
}
