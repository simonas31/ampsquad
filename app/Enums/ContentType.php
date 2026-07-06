<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ContentType: string implements HasLabel
{
    case Project = 'project';
    case Article = 'article';

    public function getLabel(): string
    {
        return match ($this) {
            self::Project => 'Project',
            self::Article => 'Article',
        };
    }
}
