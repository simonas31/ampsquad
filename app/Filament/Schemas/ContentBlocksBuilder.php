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
 * The flexible "Content Builder" field shared by Project and Page
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
                    ->label(__('admin.content_blocks.heading'))
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('text.lt')->label(__('admin.content_blocks.heading_lt'))->required(),
                            TextInput::make('text.en')->label(__('admin.content_blocks.heading_en'))->required(),
                        ]),
                        Select::make('level')
                            ->label(__('admin.content_blocks.level'))
                            ->options(['h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4'])
                            ->default('h2')
                            ->required(),
                    ])
                    ->columns(1),
                Block::make('rich_text')
                    ->label(__('admin.content_blocks.rich_text'))
                    ->schema([
                        Grid::make(2)->schema([
                            RichEditor::make('content.lt')->label(__('admin.content_blocks.content_lt'))->required(),
                            RichEditor::make('content.en')->label(__('admin.content_blocks.content_en'))->required(),
                        ]),
                    ])
                    ->columns(1),
                Block::make('image')
                    ->label(__('admin.content_blocks.image'))
                    ->schema([
                        FileUpload::make('image')
                            ->label(__('admin.content_blocks.image'))
                            ->image()
                            ->imageEditor()
                            ->disk('public')
                            ->directory('content-blocks')
                            ->required(),
                        Grid::make(2)->schema([
                            TextInput::make('caption.lt')->label(__('admin.content_blocks.caption_lt')),
                            TextInput::make('caption.en')->label(__('admin.content_blocks.caption_en')),
                        ]),
                    ])
                    ->columns(1),
                Block::make('gallery')
                    ->label(__('admin.content_blocks.gallery'))
                    ->schema([
                        FileUpload::make('images')
                            ->label(__('admin.content_blocks.gallery'))
                            ->image()
                            ->multiple()
                            ->reorderable()
                            ->disk('public')
                            ->directory('content-blocks')
                            ->required(),
                    ])
                    ->columns(1),
                Block::make('video_embed')
                    ->label(__('admin.content_blocks.video_embed'))
                    ->schema([
                        TextInput::make('url')
                            ->label(__('admin.content_blocks.video_url'))
                            ->url()
                            ->required(),
                    ])
                    ->columns(1),
                Block::make('quote')
                    ->label(__('admin.content_blocks.quote'))
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('text.lt')->label(__('admin.content_blocks.quote_lt'))->required(),
                            TextInput::make('text.en')->label(__('admin.content_blocks.quote_en'))->required(),
                        ]),
                        TextInput::make('author')
                            ->label(__('admin.content_blocks.author'))
                            ->helperText(__('admin.content_blocks.author_helper')),
                    ])
                    ->columns(1),
                Block::make('cta')
                    ->label(__('admin.content_blocks.cta'))
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('label.lt')->label(__('admin.content_blocks.button_label_lt'))->required(),
                            TextInput::make('label.en')->label(__('admin.content_blocks.button_label_en'))->required(),
                        ]),
                        TextInput::make('url')
                            ->label(__('admin.content_blocks.button_url'))
                            ->required(),
                    ])
                    ->columns(1),
            ])
            ->collapsible()
            ->blockNumbers(false);
    }
}
