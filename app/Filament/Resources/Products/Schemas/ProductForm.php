<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Product;
use App\Models\ProductCategory;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // What the shop decides. A catalog import never changes any of
                // it, so it is safe to edit whatever the product's source.
                Section::make(__('admin.products.shop_section'))
                    ->description(__('admin.products.shop_section_description'))
                    ->schema([
                        TextInput::make('stock_quantity')
                            ->label(__('admin.fields.stock'))
                            ->helperText(__('admin.products.stock_helper'))
                            ->required()
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(Product::MAX_STOCK_QUANTITY)
                            ->default(0),
                        Toggle::make('is_active')
                            ->label(__('admin.fields.is_active'))
                            ->helperText(__('admin.products.is_active_helper'))
                            ->default(true)
                            ->inline(false),
                        SpatieMediaLibraryFileUpload::make('featured_image')
                            ->label(__('admin.fields.featured_image'))
                            ->collection('featured_image')
                            ->image()
                            ->imageEditor()
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                // What a catalog import writes for a product with a SKU, and
                // overwrites on the next run. Products made by hand have no
                // SKU and are theirs to edit freely.
                Section::make(__('admin.products.catalog_section'))
                    ->description(__('admin.products.catalog_section_description'))
                    ->schema([
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
                            ->visibleOn('edit')
                            ->columnSpanFull(),
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
                        // Edited in euros, stored as whole cents. The sub-cent
                        // rule keeps "1.005" from being silently rounded to a
                        // price the admin never typed.
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
                        TextInput::make('sku')
                            ->label(__('admin.fields.sku'))
                            ->helperText(__('admin.products.sku_helper'))
                            ->maxLength(Product::MAX_SKU_LENGTH)
                            ->unique(ignoreRecord: true)
                            ->dehydrateStateUsing(self::blankToNull(...)),
                        TextInput::make('ean')
                            ->label(__('admin.fields.ean'))
                            ->regex('/^\d{8,14}$/')
                            ->validationMessages(['regex' => __('admin.catalog_import.errors.ean_format')])
                            ->dehydrateStateUsing(self::blankToNull(...)),
                        Select::make('manufacturer_id')
                            ->label(__('admin.fields.manufacturer'))
                            ->relationship('manufacturer', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('product_category_id')
                            ->label(__('admin.fields.category'))
                            ->options(fn (): array => ProductCategory::pathLabels())
                            ->searchable(),
                        TextInput::make('image_url')
                            ->label(__('admin.fields.image_url'))
                            ->helperText(__('admin.products.image_url_helper'))
                            ->url()
                            ->rule('url:http,https')
                            ->maxLength(500)
                            ->dehydrateStateUsing(self::blankToNull(...))
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * An emptied text box must reach the database as NULL: an empty string
     * would collide with the next product's empty SKU in the unique index.
     */
    private static function blankToNull(mixed $state): ?string
    {
        return filled($state) ? (string) $state : null;
    }
}
