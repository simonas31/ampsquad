<?php

declare(strict_types=1);

namespace App\Filament\Resources\ShippingMethods\Pages;

use App\Filament\Concerns\CreatesBilingualRecord;
use App\Filament\Resources\ShippingMethods\ShippingMethodResource;
use Filament\Resources\Pages\CreateRecord;

class CreateShippingMethod extends CreateRecord
{
    use CreatesBilingualRecord;

    protected static string $resource = ShippingMethodResource::class;
}
