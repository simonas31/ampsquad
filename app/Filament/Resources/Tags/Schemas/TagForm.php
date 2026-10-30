<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tags\Schemas;

use App\Filament\Schemas\BilingualFields;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TagForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                BilingualFields::make(TextInput::class, 'name', __('admin.fields.name'), required: true),
            ]);
    }
}
