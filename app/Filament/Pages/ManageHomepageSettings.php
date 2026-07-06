<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Settings\HomepageSettings;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageHomepageSettings extends SettingsPage
{
    protected static string $settings = HomepageSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('admin.homepage_settings.nav_label');
    }

    public function getTitle(): string
    {
        return __('admin.homepage_settings.nav_label');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.homepage_settings.hero_section'))
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('heroTitle.lt')->label(__('admin.homepage_settings.title_lt'))->required(),
                            TextInput::make('heroTitle.en')->label(__('admin.homepage_settings.title_en'))->required(),
                            Textarea::make('heroSubtitle.lt')->label(__('admin.homepage_settings.subtitle_lt'))->rows(2)->required(),
                            Textarea::make('heroSubtitle.en')->label(__('admin.homepage_settings.subtitle_en'))->rows(2)->required(),
                        ]),
                    ]),
                Section::make(__('admin.homepage_settings.intro_section'))
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('introTitle.lt')->label(__('admin.homepage_settings.title_lt'))->required(),
                            TextInput::make('introTitle.en')->label(__('admin.homepage_settings.title_en'))->required(),
                            Textarea::make('introContent.lt')->label(__('admin.homepage_settings.content_lt'))->rows(4)->required(),
                            Textarea::make('introContent.en')->label(__('admin.homepage_settings.content_en'))->rows(4)->required(),
                        ]),
                    ]),
                Section::make(__('admin.homepage_settings.cta_section'))
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('ctaTitle.lt')->label(__('admin.homepage_settings.title_lt'))->required(),
                            TextInput::make('ctaTitle.en')->label(__('admin.homepage_settings.title_en'))->required(),
                            TextInput::make('ctaButtonLabel.lt')->label(__('admin.homepage_settings.button_label_lt'))->required(),
                            TextInput::make('ctaButtonLabel.en')->label(__('admin.homepage_settings.button_label_en'))->required(),
                        ]),
                    ]),
            ]);
    }
}
