<?php

declare(strict_types=1);

namespace App\Filament\Resources\Recipients\Schemas;

use App\Enums\NotificationType;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class RecipientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true),
                Toggle::make('is_active')
                    ->default(true)
                    ->required(),
                CheckboxList::make('notification_types')
                    ->label('Notifications')
                    ->options(NotificationType::class)
                    ->columns(1)
                    ->helperText('Which system emails this recipient should receive.'),
            ]);
    }
}
