<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductCategories\Tables;

use App\Models\ProductCategory;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * A few hundred categories in a flat, alphabetical list. The tree shows in
 * the "parent" column and the parent filter, which narrows the list to the
 * direct children of one category. There is no bulk delete: a category that
 * holds products or sub-categories cannot be deleted at all (see
 * ProductCategoryPolicy), and a selection could hide which ones were skipped.
 */
class ProductCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Products are counted with the soft deleted ones, the same way
            // the delete rule counts them, so a category that cannot be
            // deleted never shows 0 products.
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with('parent')
                ->withCount([
                    'products' => fn (Builder $products) => $products->withTrashed(),
                    'children',
                ]))
            // Names are translatable JSON: the column's own search would
            // match the keys ("lt", "en") of every row.
            ->searchUsing(fn (Builder $query, string $search, $livewire): Builder => $query->search($search, $livewire->getActiveTableLocale()))
            ->paginated([25, 50, 100])
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->filtersFormColumns(3)
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.fields.name'))
                    ->searchable()
                    ->sortable(query: fn (Builder $query, string $direction, $livewire): Builder => $query->orderedByName($direction, $livewire->getActiveTableLocale())),
                TextColumn::make('parent.name')
                    ->label(__('admin.fields.parent'))
                    ->placeholder('—'),
                TextColumn::make('products_count')
                    ->label(__('admin.product_categories.products_count'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('children_count')
                    ->label(__('admin.product_categories.children_count'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('admin.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort(fn (Builder $query, string $direction): Builder => $query->orderedByName($direction))
            ->filters([
                SelectFilter::make('parent')
                    ->label(__('admin.fields.parent'))
                    ->options(fn (): array => ProductCategory::pathLabels())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'])
                        ? $query->where('parent_id', (int) $data['value'])
                        : $query),
                Filter::make('top_level')
                    ->label(__('admin.product_categories.top_level'))
                    ->query(fn (Builder $query): Builder => $query->whereNull('parent_id')),
                TernaryFilter::make('has_products')
                    ->label(__('admin.product_categories.has_products'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('products', fn (Builder $products) => $products->withTrashed()),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('products', fn (Builder $products) => $products->withTrashed()),
                    ),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->authorizationTooltip(),
            ]);
    }
}
