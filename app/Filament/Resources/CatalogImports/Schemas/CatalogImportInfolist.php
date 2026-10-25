<?php

declare(strict_types=1);

namespace App\Filament\Resources\CatalogImports\Schemas;

use App\Models\CatalogImport;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CatalogImportInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.catalog_import.sections.summary'))
                    ->schema([
                        TextEntry::make('original_filename')
                            ->label(__('admin.catalog_import.fields.original_filename')),
                        TextEntry::make('status')
                            ->label(__('admin.fields.status'))
                            ->badge(),
                        TextEntry::make('user.name')
                            ->label(__('admin.catalog_import.fields.user'))
                            ->placeholder('—'),
                        TextEntry::make('started_at')
                            ->label(__('admin.catalog_import.fields.started_at'))
                            ->dateTime()
                            ->placeholder('—'),
                        TextEntry::make('finished_at')
                            ->label(__('admin.catalog_import.fields.finished_at'))
                            ->dateTime()
                            ->placeholder('—'),
                        TextEntry::make('failure_message')
                            ->label(__('admin.catalog_import.fields.failure_message'))
                            ->color('danger')
                            ->visible(fn (CatalogImport $record): bool => filled($record->failure_message))
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Section::make(__('admin.catalog_import.sections.counts'))
                    ->schema([
                        TextEntry::make('total_count')
                            ->label(__('admin.catalog_import.fields.total_count'))
                            ->numeric(),
                        TextEntry::make('created_count')
                            ->label(__('admin.catalog_import.fields.created_count'))
                            ->numeric(),
                        TextEntry::make('updated_count')
                            ->label(__('admin.catalog_import.fields.updated_count'))
                            ->numeric(),
                        TextEntry::make('unchanged_count')
                            ->label(__('admin.catalog_import.fields.unchanged_count'))
                            ->numeric(),
                        TextEntry::make('skipped_count')
                            ->label(__('admin.catalog_import.fields.skipped_count'))
                            ->numeric(),
                    ])
                    ->columns(5)
                    ->columnSpanFull(),
                Section::make(__('admin.catalog_import.sections.errors'))
                    ->description(fn (CatalogImport $record): ?string => $record->skipped_count > count($record->errors ?? [])
                        ? __('admin.catalog_import.errors_capped', [
                            'shown' => count($record->errors ?? []),
                            'total' => $record->skipped_count,
                        ])
                        : null)
                    ->schema([
                        RepeatableEntry::make('errors')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('position')
                                    ->label(__('admin.catalog_import.fields.position')),
                                TextEntry::make('sku')
                                    ->label(__('admin.fields.sku'))
                                    ->placeholder('—'),
                                TextEntry::make('messages')
                                    ->label(__('admin.catalog_import.fields.messages'))
                                    ->listWithLineBreaks()
                                    ->bulleted(),
                            ])
                            ->columns(3),
                    ])
                    ->visible(fn (CatalogImport $record): bool => filled($record->errors))
                    ->columnSpanFull(),
            ]);
    }
}
