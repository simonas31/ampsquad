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
                    ->helperText('Optional caption shown with the video.'),
                Select::make('type')
                    ->options(VideoType::class)
                    ->required()
                    ->live()
                    ->native(false),
                TextInput::make('embed_url')
                    ->label('Instagram post/reel URL')
                    ->url()
                    ->required()
                    ->visible(fn (Get $get): bool => $get('type') === VideoType::InstagramEmbed->value),
                SpatieMediaLibraryFileUpload::make('video')
                    ->collection('video')
                    ->acceptedFileTypes(['video/mp4', 'video/webm', 'video/quicktime'])
                    ->maxSize(102400)
                    ->required()
                    ->visible(fn (Get $get): bool => $get('type') === VideoType::Upload->value),
                SpatieMediaLibraryFileUpload::make('poster')
                    ->collection('poster')
                    ->image()
                    ->imageEditor()
                    ->helperText('Thumbnail shown before the video plays.'),
                TextInput::make('order')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->default(true)
                    ->required(),
            ]);
    }
}
