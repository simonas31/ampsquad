<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Filament\Schemas\BilingualFields;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * For edit pages whose form uses BilingualFields.
 *
 * @mixin EditRecord
 */
trait EditsBilingualRecord
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return BilingualFields::stateFor($this->getRecord(), $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        BilingualFields::fillRecord($record, $data);
        $record->save();

        return $record;
    }
}
