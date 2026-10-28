<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Tables;

use App\Models\Product;
use App\Models\ProductCategory;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;

/**
 * Built for a catalog of thousands: every page is a few dozen rows, search
 * and filters run in the database, and nothing offers to load "all" at once.
 * There is no manual order; the list is newest first and sortable by the
 * columns that mean something (name, SKU, prices, stock).
 */
class ProductsTable
{
    private const string STOCK_IN = 'in_stock';

    private const string STOCK_OUT = 'out_of_stock';

    private const int BULK_CHUNK_SIZE = 200;

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('category.parent'))
            // Names are translatable JSON, so the column's own search would
            // match the keys ("lt", "en") of every row. Product::search reads
            // the language the list is showing, plus SKU and EAN.
            ->searchUsing(fn (Builder $query, string $search, $livewire): Builder => $query->search($search, $livewire->getActiveTableLocale()))
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->filtersFormColumns(3)
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
                    ->sortable(query: fn (Builder $query, string $direction, $livewire): Builder => $query->orderedByName($direction, $livewire->getActiveTableLocale()))
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
                TextColumn::make('price_excl_vat_cents')
                    ->label(__('admin.fields.price_excl_vat'))
                    ->money(Product::CURRENCY, divideBy: 100)
                    ->sortable(),
                // Worked out from the price without VAT, so it sorts the
                // same way: the order of the two never differs.
                TextColumn::make('price_incl_vat')
                    ->label(__('admin.fields.price_incl_vat'))
                    ->state(fn (Product $record): int => $record->priceInclVatCents())
                    ->money(Product::CURRENCY, divideBy: 100)
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('price_excl_vat_cents', $direction)),
                // Quantity is the shop's to manage, so it is edited right in
                // the list; a catalog import never touches it.
                TextInputColumn::make('stock_quantity')
                    ->label(__('admin.fields.stock'))
                    ->type('number')
                    ->inputMode('numeric')
                    ->step(1)
                    ->rules(['required', 'integer', 'min:0', 'max:'.Product::MAX_STOCK_QUANTITY])
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->label(__('admin.fields.is_active')),
                TextColumn::make('ean')
                    ->label(__('admin.fields.ean'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label(__('admin.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
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
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        self::STOCK_IN => $query->where('stock_quantity', '>', 0),
                        self::STOCK_OUT => $query->where('stock_quantity', 0),
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
            // The bulk actions work on a whole filtered result (select all),
            // a chunk at a time, with one UPDATE per chunk.
            ->toolbarActions([
                BulkActionGroup::make([
                    self::setActiveBulkAction('activate', true),
                    self::setActiveBulkAction('deactivate', false),
                    self::setStockBulkAction(),
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Writes the same values to every selected product with one UPDATE per
     * chunk. Soft deleted products are included, so a list showing them
     * changes them too, and no model events fire: a status or stock change
     * must not regenerate slugs.
     *
     * @param  LazyCollection<int, Product>  $records
     * @param  array<string, mixed>  $values
     */
    private static function updateInChunks(LazyCollection $records, array $values): void
    {
        $records->chunk(self::BULK_CHUNK_SIZE)->each(fn (LazyCollection $chunk) => Product::query()
            ->withTrashed()
            ->whereKey($chunk->map(fn (Product $product): int => $product->getKey())->all())
            ->update($values));
    }

    private static function setActiveBulkAction(string $name, bool $isActive): BulkAction
    {
        return BulkAction::make($name)
            ->label(__("admin.products.bulk.{$name}"))
            ->icon($isActive ? Heroicon::OutlinedEye : Heroicon::OutlinedEyeSlash)
            ->requiresConfirmation()
            ->authorizeIndividualRecords('update')
            ->chunkSelectedRecords(self::BULK_CHUNK_SIZE)
            ->action(fn (LazyCollection $records) => self::updateInChunks($records, ['is_active' => $isActive]))
            ->successNotificationTitle(__("admin.products.bulk.{$name}_done"))
            ->deselectRecordsAfterCompletion();
    }

    private static function setStockBulkAction(): BulkAction
    {
        return BulkAction::make('setStock')
            ->label(__('admin.products.bulk.set_stock'))
            ->icon(Heroicon::OutlinedArchiveBox)
            ->authorizeIndividualRecords('update')
            ->chunkSelectedRecords(self::BULK_CHUNK_SIZE)
            ->schema([
                TextInput::make('stock_quantity')
                    ->label(__('admin.fields.stock'))
                    ->helperText(__('admin.products.bulk.set_stock_helper'))
                    ->required()
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->maxValue(Product::MAX_STOCK_QUANTITY)
                    ->default(0),
            ])
            ->action(fn (LazyCollection $records, array $data) => self::updateInChunks($records, ['stock_quantity' => (int) $data['stock_quantity']]))
            ->successNotificationTitle(__('admin.products.bulk.set_stock_done'))
            ->deselectRecordsAfterCompletion();
    }
}
