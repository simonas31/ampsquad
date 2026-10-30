<?php

declare(strict_types=1);

namespace App\Filament\Resources\Capabilities\Schemas;

use App\Filament\Schemas\BilingualFields;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CapabilityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                BilingualFields::make(TextInput::class, 'name', __('admin.fields.name'), required: true),
                TextInput::make('icon')
                    ->label(__('admin.fields.icon'))
                    ->helperText(__('admin.capabilities.icon_helper')),
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
