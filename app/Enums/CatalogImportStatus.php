<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CatalogImportStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return __('admin.enums.catalog_import_status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Processing => 'warning',
            self::Completed => 'success',
            self::Failed => 'danger',
        };
    }

    /**
     * Whether the run is over, one way or the other.
     */
    public function isFinished(): bool
    {
        return $this === self::Completed || $this === self::Failed;
    }
}
