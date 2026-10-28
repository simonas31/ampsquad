<?php

declare(strict_types=1);

namespace App\Filament\Resources\ShippingMethods\Schemas;

use App\Models\Product;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Number;

class ShippingMethodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('admin.fields.name'))
                    ->required()
                    ->maxLength(100),
                Textarea::make('description')
                    ->label(__('admin.fields.description'))
                    ->helperText(__('admin.shipping_methods.description_helper'))
                    ->rows(3)
                    ->maxLength(500),
                // Edited in euros without VAT, stored as whole cents, like a
                // product's price. Free is allowed, which pickup usually is.
                TextInput::make('price_excl_vat_cents')
                    ->label(__('admin.fields.price_excl_vat'))
                    ->helperText(fn (mixed $state): string => self::priceHelperText($state))
                    ->live(onBlur: true)
                    ->required()
                    ->numeric()
                    ->prefix('€')
                    ->step(0.01)
                    ->minValue(0)
                    ->maxValue(Product::MAX_PRICE_EXCL_VAT_CENTS / 100)
                    ->rule('decimal:0,2')
                    ->default(0)
                    ->formatStateUsing(fn (?int $state): ?string => $state === null
                        ? null
                        : number_format($state / 100, 2, '.', ''))
                    ->dehydrateStateUsing(fn (mixed $state): int => (int) round((float) $state * 100)),
                Toggle::make('is_pickup')
                    ->label(__('admin.shipping_methods.is_pickup'))
                    ->helperText(__('admin.shipping_methods.is_pickup_helper')),
                Toggle::make('is_active')
                    ->label(__('admin.fields.is_active'))
                    ->helperText(__('admin.shipping_methods.is_active_helper'))
                    ->default(true),
            ]);
    }

    /**
     * What the customer will pay for it with VAT, once the typed price is a
     * valid amount.
     */
    private static function priceHelperText(mixed $state): string
    {
        $helper = __('admin.shipping_methods.price_helper');

        if (! is_numeric($state) || (float) $state < 0 || (float) $state > Product::MAX_PRICE_EXCL_VAT_CENTS / 100) {
            return $helper;
        }

        $inclVatCents = Product::addVat((int) round((float) $state * 100));

        return $helper.' '.__('admin.products.price_incl_vat_hint', [
            'rate' => Product::vatRatePercent(),
            'price' => Number::currency($inclVatCents / 100, Product::CURRENCY, app()->getLocale()),
        ]);
    }
}
