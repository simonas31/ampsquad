<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\Schemas;

use App\Enums\ProjectStatus;
use App\Filament\Schemas\BilingualFields;
use App\Filament\Schemas\ContentBlocksBuilder;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

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
                BilingualFields::make(TextInput::class, 'title', __('admin.fields.title'), required: true, requiredWith: ['excerpt', 'location']),
                BilingualFields::slug(),
                BilingualFields::make(
                    Textarea::class,
                    'excerpt',
                    __('admin.fields.excerpt'),
                    fn (Textarea $field) => $field->rows(2),
                ),
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
                BilingualFields::make(TextInput::class, 'location', __('admin.fields.location')),
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
