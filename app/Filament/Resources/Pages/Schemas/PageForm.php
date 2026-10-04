<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pages\Schemas;

use App\Filament\Schemas\ContentBlocksBuilder;
use App\Models\Page;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.pages.main_section'))
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('title')
                            ->label(__('admin.fields.title'))
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state ?? '')))
                            ->columnSpanFull(),
                        TextInput::make('key')
                            ->label(__('admin.pages.key'))
                            ->required()
                            ->unique(Page::class, 'key', ignoreRecord: true)
                            ->helperText(__('admin.pages.key_helper'))
                            ->disabled(fn (?Page $record): bool => $record?->isProtected() ?? false),
                        TextInput::make('slug')
                            ->label(__('admin.fields.slug'))
                            ->required(),
                        Checkbox::make('show_in_header')
                            ->label(__('admin.pages.show_in_header'))
                            ->helperText(fn (?Page $record): ?string => $record?->isAlwaysInMenus() ? __('admin.pages.always_in_menus_helper') : null)
                            ->disabled(fn (?Page $record): bool => $record?->isAlwaysInMenus() ?? false),
                        Checkbox::make('show_in_footer')
                            ->label(__('admin.pages.show_in_footer'))
                            ->helperText(fn (?Page $record): ?string => $record?->isAlwaysInMenus() ? __('admin.pages.always_in_menus_helper') : null)
                            ->disabled(fn (?Page $record): bool => $record?->isAlwaysInMenus() ?? false),
                        SpatieMediaLibraryFileUpload::make('featured_image')
                            ->label(__('admin.fields.featured_image'))
                            ->collection('featured_image')
                            ->image()
                            ->imageEditor()
                            ->columnSpanFull(),
                    ]),
                Section::make(__('admin.fields.blocks'))
                    ->columnSpanFull()
                    ->schema([
                        ContentBlocksBuilder::make()->hiddenLabel(),
                    ]),
            ]);
    }
}
