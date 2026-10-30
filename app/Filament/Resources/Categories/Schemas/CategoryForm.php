<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categories\Schemas;

use App\Filament\Schemas\BilingualFields;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

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
                BilingualFields::make(TextInput::class, 'name', __('admin.fields.name'), required: true, requiredWith: ['description']),
                BilingualFields::make(Textarea::class, 'description', __('admin.fields.description')),
                TextInput::make('order')
                    ->label(__('admin.fields.order'))
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
