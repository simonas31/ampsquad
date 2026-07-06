<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pages\Schemas;

use App\Filament\Schemas\ContentBlocksBuilder;
use App\Models\Page;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('key')
                    ->label(__('admin.pages.key'))
                    ->required()
                    ->unique(Page::class, 'key', ignoreRecord: true)
                    ->helperText(__('admin.pages.key_helper'))
                    ->disabled(fn (?Page $record): bool => $record?->isProtected() ?? false),
                TextInput::make('title')
                    ->label(__('admin.fields.title'))
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state ?? ''))),
                TextInput::make('slug')
                    ->label(__('admin.fields.slug'))
                    ->required(),
                SpatieMediaLibraryFileUpload::make('featured_image')
                    ->label(__('admin.fields.featured_image'))
                    ->collection('featured_image')
                    ->image()
                    ->imageEditor(),
                ContentBlocksBuilder::make(),
            ]);
    }
}
