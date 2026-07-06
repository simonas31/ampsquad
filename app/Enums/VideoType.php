<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum VideoType: string implements HasLabel
{
    case InstagramEmbed = 'instagram_embed';
    case Upload = 'upload';

    public function getLabel(): string
    {
        return __('admin.enums.video_type.'.$this->value);
    }
}
