<?php

declare(strict_types=1);

namespace App\Filament\Resources\CalculatorCategories\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CalculatorCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('admin.fields.name'))
                    ->required(),
                TextInput::make('icon')
                    ->label(__('admin.fields.icon'))
                    ->helperText(__('admin.calculator_categories.icon_helper')),
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
