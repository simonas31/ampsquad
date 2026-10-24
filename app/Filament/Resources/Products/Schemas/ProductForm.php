<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Product;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('admin.fields.name'))
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('slug')
                    ->label(__('admin.fields.slug'))
                    ->helperText(__('admin.slug.helper'))
                    ->disabled()
                    ->saved(false)
                    ->visibleOn('edit'),
                Textarea::make('excerpt')
                    ->label(__('admin.fields.excerpt'))
                    ->helperText(__('admin.products.excerpt_helper'))
                    ->rows(2)
                    ->maxLength(255)
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->label(__('admin.fields.description'))
                    ->rows(6)
                    ->maxLength(5000)
                    ->columnSpanFull(),
                SpatieMediaLibraryFileUpload::make('featured_image')
                    ->label(__('admin.fields.featured_image'))
                    ->collection('featured_image')
                    ->image()
                    ->imageEditor()
                    ->columnSpanFull(),
                // Edited in euros, stored as whole cents. The sub-cent rule
                // keeps "1.005" from being silently rounded to a price the
                // admin never typed.
                TextInput::make('price_cents')
                    ->label(__('admin.fields.price'))
                    ->helperText(__('admin.products.price_helper'))
                    ->required()
                    ->numeric()
                    ->prefix('€')
                    ->step(0.01)
                    ->minValue(0.01)
                    ->maxValue(Product::MAX_PRICE_CENTS / 100)
                    ->rule('decimal:0,2')
                    ->formatStateUsing(fn (?int $state): ?string => $state === null
                        ? null
                        : number_format($state / 100, 2, '.', ''))
                    ->dehydrateStateUsing(fn (mixed $state): int => (int) round((float) $state * 100)),
                TextInput::make('order')
                    ->label(__('admin.fields.order'))
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
                Toggle::make('is_active')
                    ->label(__('admin.fields.is_active'))
                    ->helperText(__('admin.products.is_active_helper'))
                    ->default(true),
            ]);
    }
}
