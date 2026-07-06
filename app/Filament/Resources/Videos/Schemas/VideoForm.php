<?php

declare(strict_types=1);

namespace App\Filament\Resources\Videos\Schemas;

use App\Enums\VideoType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class VideoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label(__('admin.fields.title'))
                    ->helperText(__('admin.videos.title_helper')),
                Select::make('type')
                    ->label(__('admin.fields.type'))
                    ->options(VideoType::class)
                    ->required()
                    ->live()
                    ->native(false),
                TextInput::make('embed_url')
                    ->label(__('admin.videos.instagram_url'))
                    ->url()
                    ->required()
                    ->visible(fn (Get $get): bool => $get('type') === VideoType::InstagramEmbed->value),
                SpatieMediaLibraryFileUpload::make('video')
                    ->label(__('admin.fields.video'))
                    ->collection('video')
                    ->acceptedFileTypes(['video/mp4', 'video/webm', 'video/quicktime'])
                    ->maxSize(102400)
                    ->required()
                    ->visible(fn (Get $get): bool => $get('type') === VideoType::Upload->value),
                SpatieMediaLibraryFileUpload::make('poster')
                    ->label(__('admin.fields.poster'))
                    ->collection('poster')
                    ->image()
                    ->imageEditor()
                    ->helperText(__('admin.videos.poster_helper')),
                TextInput::make('order')
                    ->label(__('admin.fields.order'))
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->label(__('admin.fields.is_active'))
                    ->default(true)
                    ->required(),
            ]);
    }
}
