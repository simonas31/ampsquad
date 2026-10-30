<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Filament\Schemas\BilingualFields;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * For create pages whose form uses BilingualFields.
 *
 * @mixin CreateRecord
 */
trait CreatesBilingualRecord
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $record = new ($this->getModel());

        BilingualFields::fillRecord($record, $data);
        $record->save();

        return $record;
    }
}
