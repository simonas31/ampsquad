<?php

declare(strict_types=1);

namespace App\Filament\Resources\Capabilities\Pages;

use App\Filament\Concerns\CreatesBilingualRecord;
use App\Filament\Resources\Capabilities\CapabilityResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCapability extends CreateRecord
{
    use CreatesBilingualRecord;

    protected static string $resource = CapabilityResource::class;
}
