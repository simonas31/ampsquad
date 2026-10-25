<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Tables;

use App\Models\Product;
use App\Models\ProductCategory;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductsTable
{
    private const string STOCK_IN = 'in_stock';

    private const string STOCK_OUT = 'out_of_stock';

    private const string STOCK_UNTRACKED = 'untracked';

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('category.parent'))
            ->columns([
                // Catalog products have no upload, only the supplier's image URL.
                SpatieMediaLibraryImageColumn::make('featured_image')
                    ->label(__('admin.fields.featured_image'))
                    ->collection('featured_image')
                    ->conversion('thumbnail')
                    ->defaultImageUrl(fn (Product $record): ?string => $record->image_url),
                TextColumn::make('sku')
                    ->label(__('admin.fields.sku'))
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('name')
                    ->label(__('admin.fields.name'))
                    ->searchable()
                    ->limit(60),
                TextColumn::make('category.name')
                    ->label(__('admin.fields.category'))
                    ->description(fn (Product $record): ?string => $record->category?->parent?->name)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('manufacturer.name')
                    ->label(__('admin.fields.manufacturer'))
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('price_cents')
                    ->label(__('admin.fields.price'))
                    ->money('EUR', divideBy: 100)
                    ->sortable(),
                TextColumn::make('stock_quantity')
                    ->label(__('admin.fields.stock'))
                    ->badge()
                    ->numeric()
                    ->placeholder(__('admin.products.stock_untracked'))
                    ->color(fn (?int $state): string => match (true) {
                        $state === null => 'gray',
                        $state === 0 => 'danger',
                        default => 'success',
                    })
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->label(__('admin.fields.is_active')),
                TextColumn::make('ean')
                    ->label(__('admin.fields.ean'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('order')
                    ->label(__('admin.fields.order'))
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label(__('admin.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('order')
            ->reorderable('order')
            ->filters([
                SelectFilter::make('category')
                    ->label(__('admin.fields.category'))
                    ->options(fn (): array => ProductCategory::pathLabels())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'])
                        ? $query->whereIn('product_category_id', ProductCategory::idsWithDescendants((int) $data['value']))
                        : $query),
                SelectFilter::make('manufacturer')
                    ->label(__('admin.fields.manufacturer'))
                    ->relationship('manufacturer', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('stock')
                    ->label(__('admin.fields.stock'))
                    ->options([
                        self::STOCK_IN => __('admin.products.stock_filter.in_stock'),
                        self::STOCK_OUT => __('admin.products.stock_filter.out_of_stock'),
                        self::STOCK_UNTRACKED => __('admin.products.stock_filter.untracked'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        self::STOCK_IN => $query->where('stock_quantity', '>', 0),
                        self::STOCK_OUT => $query->where('stock_quantity', 0),
                        self::STOCK_UNTRACKED => $query->whereNull('stock_quantity'),
                        default => $query,
                    }),
                TernaryFilter::make('is_active')
                    ->label(__('admin.fields.is_active')),
                TernaryFilter::make('has_sku')
                    ->label(__('admin.products.from_catalog'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('sku'),
                        false: fn (Builder $query): Builder => $query->whereNull('sku'),
                    ),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            // No force delete: a product can be in a cart (and later on an
            // order), so it is only ever soft deleted and can be restored.
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
