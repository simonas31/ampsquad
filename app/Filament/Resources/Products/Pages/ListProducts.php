<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\CatalogImports\CatalogImportResource;
use App\Filament\Resources\Products\ProductResource;
use App\Jobs\ImportCatalogJob;
use App\Models\CatalogImport;
use App\Services\Catalog\CatalogFileReader;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use LaraZeus\SpatieTranslatable\Resources\Pages\ListRecords\Concerns\Translatable;

class ListProducts extends ListRecords
{
    use Translatable;

    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            $this->downloadCatalogTemplateAction(),
            $this->importCatalogAction(),
            CreateAction::make(),
        ];
    }

    private function downloadCatalogTemplateAction(): Action
    {
        return Action::make('downloadCatalogTemplate')
            ->label(__('admin.catalog_import.download_template'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->action(fn () => response()->download(
                resource_path('catalog/catalog.template.json'),
                'catalog.template.json',
            ));
    }

    /**
     * The import itself runs on the queue (see ImportCatalogJob); this only
     * stores the upload and starts it. Progress and row errors are on the
     * run's page.
     */
    private function importCatalogAction(): Action
    {
        return Action::make('importCatalog')
            ->label(__('admin.catalog_import.action'))
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('gray')
            ->modalHeading(__('admin.catalog_import.modal_heading'))
            ->modalDescription(__('admin.catalog_import.modal_description'))
            ->modalSubmitActionLabel(__('admin.catalog_import.submit'))
            ->schema([
                // Text/plain is accepted as well: a JSON file with a byte
                // order mark is detected as plain text, and the importer
                // reports a file that is not JSON in plain words anyway.
                FileUpload::make('file')
                    ->label(__('admin.catalog_import.file'))
                    ->helperText(__('admin.catalog_import.file_helper', ['max' => CatalogFileReader::MAX_BYTES / 1024 / 1024]))
                    ->disk('local')
                    ->directory('imports')
                    ->acceptedFileTypes(['application/json', 'text/json', 'text/plain'])
                    ->maxSize(CatalogFileReader::MAX_BYTES / 1024)
                    ->storeFileNamesIn('original_filename')
                    ->required(),
            ])
            ->action(function (array $data): void {
                $import = CatalogImport::query()->create([
                    'user_id' => auth()->id(),
                    'original_filename' => $data['original_filename'] ?? basename($data['file']),
                    'file_path' => $data['file'],
                ]);

                ImportCatalogJob::dispatch($import);

                Notification::make()
                    ->success()
                    ->title(__('admin.catalog_import.queued_title'))
                    ->body(__('admin.catalog_import.queued_body'))
                    ->actions([
                        Action::make('view')
                            ->label(__('admin.catalog_import.view'))
                            ->url(CatalogImportResource::getUrl('view', ['record' => $import])),
                    ])
                    ->send();
            });
    }
}
