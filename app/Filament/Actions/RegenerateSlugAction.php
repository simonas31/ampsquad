<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Filament\Schemas\BilingualFields;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

/**
 * Rebuilds the chosen languages' slugs from their saved titles. Live records
 * keep their old URL as a 301, which is why this is an explicit, confirmed
 * step instead of happening whenever a title is edited.
 *
 * For use on edit pages whose model uses the HasSlug concern and whose form
 * shows its slugs with BilingualFields::slug().
 */
class RegenerateSlugAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'regenerateSlug';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $locales = BilingualFields::locales();

        $this
            ->label(__('admin.slug.regenerate'))
            ->icon(Heroicon::ArrowPath)
            ->color('gray')
            ->modalHeading(__('admin.slug.regenerate'))
            ->modalDescription(__('admin.slug.regenerate_description'))
            ->schema([
                CheckboxList::make('locales')
                    ->label(__('admin.slug.languages'))
                    ->options(collect($locales)->mapWithKeys(fn (string $locale): array => [
                        $locale => __('admin.locale_switcher.'.$locale),
                    ])->all())
                    ->default($locales)
                    ->required(),
            ])
            ->action(function (array $data, EditRecord $livewire): void {
                $record = $livewire->getRecord();
                $regenerated = false;

                foreach ($data['locales'] as $locale) {
                    if (! $record->regenerateSlug($locale)) {
                        continue;
                    }

                    $livewire->data['slug'][$locale] = $record->getTranslation('slug', $locale);
                    $regenerated = true;
                }

                if (! $regenerated) {
                    Notification::make()->title(__('admin.slug.unchanged'))->warning()->send();

                    return;
                }

                Notification::make()->title(__('admin.slug.regenerated'))->success()->send();
            });
    }
}
