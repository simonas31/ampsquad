<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Settings\GeneralSettings;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageGeneralSettings extends SettingsPage
{
    protected static string $settings = GeneralSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('admin.general_settings.nav_label');
    }

    public function getTitle(): string
    {
        return __('admin.general_settings.nav_label');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)
                    ->schema([
                        TextInput::make('email')
                            ->label(__('admin.fields.email'))
                            ->email()
                            ->required(),
                        TextInput::make('phone')
                            ->label(__('admin.fields.phone'))
                            ->tel()
                            ->required(),
                        TextInput::make('address')
                            ->label(__('admin.fields.location'))
                            ->columnSpanFull()
                            ->required(),
                        TextInput::make('facebookUrl')
                            ->label(__('admin.general_settings.facebook_url'))
                            ->url(),
                        TextInput::make('instagramUrl')
                            ->label(__('admin.general_settings.instagram_url'))
                            ->url(),
                        TextInput::make('linkedinUrl')
                            ->label(__('admin.general_settings.linkedin_url'))
                            ->url(),
                    ]),
            ]);
    }
}
