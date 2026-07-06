<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categories\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('parent_id')
                    ->label(__('admin.fields.parent'))
                    ->relationship('parent', 'name')
                    ->searchable()
                    ->preload(),
                TextInput::make('name')
                    ->label(__('admin.fields.name'))
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state ?? ''))),
                TextInput::make('slug')
                    ->label(__('admin.fields.slug'))
                    ->required(),
                Textarea::make('description')
                    ->label(__('admin.fields.description'))
                    ->columnSpanFull(),
                TextInput::make('order')
                    ->label(__('admin.fields.order'))
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
