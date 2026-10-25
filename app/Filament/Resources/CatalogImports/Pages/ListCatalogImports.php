<?php

declare(strict_types=1);

namespace App\Filament\Resources\CatalogImports\Pages;

use App\Filament\Resources\CatalogImports\CatalogImportResource;
use Filament\Resources\Pages\ListRecords;

class ListCatalogImports extends ListRecords
{
    protected static string $resource = CatalogImportResource::class;
}
