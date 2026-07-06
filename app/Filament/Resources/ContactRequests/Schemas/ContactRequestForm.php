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
                    ->disabled(),
                TextInput::make('email')
                    ->disabled(),
                TextInput::make('phone')
                    ->disabled(),
                Textarea::make('message')
                    ->disabled()
                    ->rows(5)
                    ->columnSpanFull(),
                TextInput::make('ip_address')
                    ->label('IP address')
                    ->disabled(),
                Select::make('status')
                    ->options(ContactRequestStatus::class)
                    ->required(),
            ]);
    }
}
