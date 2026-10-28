<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Settings\ShopSettings;
use BackedEnum;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageShopSettings extends SettingsPage
{
    protected static string $settings = ShopSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static string|\UnitEnum|null $navigationGroup = 'Shop';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('admin.shop_settings.nav_label');
    }

    public function getTitle(): string
    {
        return __('admin.shop_settings.nav_label');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Toggle::make('enabled')
                    ->label(__('admin.shop_settings.enabled'))
                    ->helperText(__('admin.shop_settings.enabled_helper')),
                Toggle::make('checkoutEnabled')
                    ->label(__('admin.shop_settings.checkout_enabled'))
                    ->helperText(__('admin.shop_settings.checkout_enabled_helper')),
            ]);
    }
}
