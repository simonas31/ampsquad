<?php

declare(strict_types=1);

namespace App\Filament\Resources\CatalogImports\Tables;

use App\Enums\CatalogImportStatus;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CatalogImportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('admin.fields.created_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('original_filename')
                    ->label(__('admin.catalog_import.fields.original_filename'))
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('admin.fields.status'))
                    ->badge(),
                TextColumn::make('created_count')
                    ->label(__('admin.catalog_import.fields.created_count'))
                    ->numeric(),
                TextColumn::make('updated_count')
                    ->label(__('admin.catalog_import.fields.updated_count'))
                    ->numeric(),
                TextColumn::make('unchanged_count')
                    ->label(__('admin.catalog_import.fields.unchanged_count'))
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('skipped_count')
                    ->label(__('admin.catalog_import.fields.skipped_count'))
                    ->numeric()
                    ->color(fn (int $state): ?string => $state > 0 ? 'warning' : null),
                TextColumn::make('user.name')
                    ->label(__('admin.catalog_import.fields.user'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            // A run takes minutes; this keeps the list moving without a reload.
            ->poll('5s')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.fields.status'))
                    ->options(CatalogImportStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
