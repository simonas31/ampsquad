<?php

declare(strict_types=1);

namespace App\Filament\Schemas;

use App\Enums\ContentType;
use App\Enums\ProjectStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

/**
 * Shared by ProjectForm and ArticleForm — both are the same underlying
 * Project model/table, differing only in the fixed content_type and
 * whether the project-only fields (location/client/completed_at) show.
 */
class ContentForm
{
    public static function configure(Schema $schema, ContentType $type): Schema
    {
        return $schema
            ->components([
                Hidden::make('content_type')->default($type->value),
                Select::make('category_id')
                    ->label('Category')
                    ->relationship(
                        'category',
                        'name',
                        fn ($query) => $query->where('applies_to', $type->value)->orWhereNull('applies_to'),
                    )
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('tags')
                    ->relationship('tags', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),
                TextInput::make('title')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state ?? '')))
                    ->columnSpanFull(),
                TextInput::make('slug')
                    ->required(),
                Textarea::make('excerpt')
                    ->rows(2)
                    ->columnSpanFull(),
                SpatieMediaLibraryFileUpload::make('featured_image')
                    ->collection('featured_image')
                    ->image()
                    ->imageEditor()
                    ->columnSpanFull(),
                SpatieMediaLibraryFileUpload::make('gallery')
                    ->collection('gallery')
                    ->image()
                    ->multiple()
                    ->reorderable()
                    ->columnSpanFull(),
                ContentBlocksBuilder::make()
                    ->columnSpanFull(),
                Select::make('status')
                    ->options(ProjectStatus::class)
                    ->default(ProjectStatus::Draft)
                    ->required(),
                DateTimePicker::make('published_at'),
                Toggle::make('is_featured'),
                TextInput::make('order')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('location')
                    ->visible($type === ContentType::Project),
                TextInput::make('client_name')
                    ->label('Client')
                    ->visible($type === ContentType::Project),
                DatePicker::make('completed_at')
                    ->visible($type === ContentType::Project),
            ]);
    }
}
