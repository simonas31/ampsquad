<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ContactRequestStatus: string implements HasLabel
{
    case New = 'new';
    case Read = 'read';
    case Archived = 'archived';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Read => 'Read',
            self::Archived => 'Archived',
        };
    }
}
