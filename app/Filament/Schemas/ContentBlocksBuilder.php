<?php

declare(strict_types=1);

namespace App\Filament\Schemas;

use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;

/**
 * The flexible "Content Builder" field shared by Project/Article and Page
 * resources. Blocks share one structure across locales — only the leaf
 * text fields carry both languages (see App\Models\Concerns\
 * HasTranslatableBlocks for why) — so each translatable field here is a
 * two-column grid of plain "field.lt" / "field.en" inputs rather than a
 * locale-switched single input.
 */
class ContentBlocksBuilder
{
    public static function make(string $name = 'blocks'): Builder
    {
        return Builder::make($name)
            ->blocks([
                Block::make('heading')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('text.lt')->label('Heading (LT)')->required(),
                            TextInput::make('text.en')->label('Heading (EN)')->required(),
                        ]),
                        Select::make('level')
                            ->options(['h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4'])
                            ->default('h2')
                            ->required(),
                    ])
                    ->columns(1),
                Block::make('rich_text')
                    ->label('Rich text')
                    ->schema([
                        Grid::make(2)->schema([
                            RichEditor::make('content.lt')->label('Content (LT)')->required(),
                            RichEditor::make('content.en')->label('Content (EN)')->required(),
                        ]),
                    ])
                    ->columns(1),
                Block::make('image')
                    ->schema([
                        FileUpload::make('image')
                            ->image()
                            ->imageEditor()
                            ->disk('public')
                            ->directory('content-blocks')
                            ->required(),
                        Grid::make(2)->schema([
                            TextInput::make('caption.lt')->label('Caption (LT)'),
                            TextInput::make('caption.en')->label('Caption (EN)'),
                        ]),
                    ])
                    ->columns(1),
                Block::make('gallery')
                    ->schema([
                        FileUpload::make('images')
                            ->image()
                            ->multiple()
                            ->reorderable()
                            ->disk('public')
                            ->directory('content-blocks')
                            ->required(),
                    ])
                    ->columns(1),
                Block::make('video_embed')
                    ->label('Video embed')
                    ->schema([
                        TextInput::make('url')
                            ->label('Video URL (YouTube, Vimeo, etc.)')
                            ->url()
                            ->required(),
                    ])
                    ->columns(1),
                Block::make('quote')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('text.lt')->label('Quote (LT)')->required(),
                            TextInput::make('text.en')->label('Quote (EN)')->required(),
                        ]),
                        TextInput::make('author')
                            ->helperText('Not translated — proper nouns stay as-is in both languages.'),
                    ])
                    ->columns(1),
                Block::make('cta')
                    ->label('Call to action')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('label.lt')->label('Button label (LT)')->required(),
                            TextInput::make('label.en')->label('Button label (EN)')->required(),
                        ]),
                        TextInput::make('url')
                            ->label('Button URL')
                            ->required(),
                    ])
                    ->columns(1),
            ])
            ->collapsible()
            ->blockNumbers(false);
    }
}
