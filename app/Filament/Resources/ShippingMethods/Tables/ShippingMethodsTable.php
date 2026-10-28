<?php

declare(strict_types=1);

namespace App\Filament\Resources\ShippingMethods\Tables;

use App\Models\Product;
use App\Models\ShippingMethod;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Number;

class ShippingMethodsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.fields.name'))
                    ->searchable(),
                TextColumn::make('price_excl_vat_cents')
                    ->label(__('admin.fields.price_excl_vat'))
                    ->formatStateUsing(fn (int $state): string => Number::currency($state / 100, Product::CURRENCY, app()->getLocale()))
                    ->sortable(),
                TextColumn::make('price_incl_vat')
                    ->label(__('admin.fields.price_incl_vat'))
                    ->state(fn (ShippingMethod $record): string => Number::currency($record->priceInclVatCents() / 100, Product::CURRENCY, app()->getLocale())),
                IconColumn::make('is_pickup')
                    ->label(__('admin.shipping_methods.is_pickup'))
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label(__('admin.fields.is_active'))
                    ->boolean(),
            ])
            ->defaultSort('price_excl_vat_cents')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('admin.fields.is_active')),
                TernaryFilter::make('is_pickup')
                    ->label(__('admin.shipping_methods.is_pickup')),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
