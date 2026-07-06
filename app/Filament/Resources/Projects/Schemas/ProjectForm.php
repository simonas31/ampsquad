<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\Schemas;

use App\Enums\ProjectStatus;
use App\Filament\Schemas\ContentBlocksBuilder;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('category_id')
                    ->label(__('admin.fields.category'))
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('tags')
                    ->label(__('admin.fields.tags'))
                    ->relationship('tags', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),
                TextInput::make('title')
                    ->label(__('admin.fields.title'))
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state ?? '')))
                    ->columnSpanFull(),
                TextInput::make('slug')
                    ->label(__('admin.fields.slug'))
                    ->required(),
                Textarea::make('excerpt')
                    ->label(__('admin.fields.excerpt'))
                    ->rows(2)
                    ->columnSpanFull(),
                SpatieMediaLibraryFileUpload::make('featured_image')
                    ->label(__('admin.fields.featured_image'))
                    ->collection('featured_image')
                    ->image()
                    ->imageEditor()
                    ->columnSpanFull(),
                SpatieMediaLibraryFileUpload::make('gallery')
                    ->label(__('admin.fields.gallery'))
                    ->collection('gallery')
                    ->image()
                    ->multiple()
                    ->reorderable()
                    ->columnSpanFull(),
                ContentBlocksBuilder::make()
                    ->label(__('admin.fields.blocks'))
                    ->columnSpanFull(),
                Select::make('status')
                    ->label(__('admin.fields.status'))
                    ->options(ProjectStatus::class)
                    ->default(ProjectStatus::Draft)
                    ->required(),
                DateTimePicker::make('published_at')
                    ->label(__('admin.fields.published_at'))
                    ->native(false)
                    ->displayFormat('Y-m-d H:i:s')
                    ->format('Y-m-d H:i:s')
                    ->seconds(),
                Toggle::make('is_featured')
                    ->label(__('admin.fields.is_featured')),
                TextInput::make('order')
                    ->label(__('admin.fields.order'))
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('location')
                    ->label(__('admin.fields.location')),
                TextInput::make('client_name')
                    ->label(__('admin.fields.client')),
                DatePicker::make('completed_at')
                    ->label(__('admin.fields.completed_at'))
                    ->native(false)
                    ->displayFormat('Y-m-d')
                    ->format('Y-m-d'),
            ]);
    }
}
