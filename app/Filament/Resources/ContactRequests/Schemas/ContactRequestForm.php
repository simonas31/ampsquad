<?php

declare(strict_types=1);

namespace App\Filament\Resources\ContactRequests\Schemas;

use App\Enums\ContactRequestStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ContactRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('admin.fields.name'))
                    ->disabled(),
                TextInput::make('email')
                    ->label(__('admin.fields.email'))
                    ->disabled(),
                TextInput::make('phone')
                    ->label(__('admin.fields.phone'))
                    ->disabled(),
                Textarea::make('message')
                    ->label(__('admin.fields.message'))
                    ->disabled()
                    ->rows(5)
                    ->columnSpanFull(),
                TextInput::make('ip_address')
                    ->label(__('admin.fields.ip_address'))
                    ->disabled(),
                Select::make('status')
                    ->label(__('admin.fields.status'))
                    ->options(ContactRequestStatus::class)
                    ->required(),
            ]);
    }
}
