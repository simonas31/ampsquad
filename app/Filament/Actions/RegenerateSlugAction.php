<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

/**
 * Rebuilds the edited locale's slug from its saved title. Live records keep
 * their old URL as a 301, which is why this is an explicit, confirmed step
 * instead of happening whenever a title is edited.
 *
 * For use on edit pages that use LaraZeus's Translatable concern and whose
 * model uses the HasSlug concern.
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

        $this
            ->label(__('admin.slug.regenerate'))
            ->icon(Heroicon::ArrowPath)
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading(__('admin.slug.regenerate'))
            ->modalDescription(__('admin.slug.regenerate_description'))
            ->action(function (EditRecord $livewire): void {
                $record = $livewire->getRecord();
                $locale = $livewire->activeLocale;

                if (! $record->regenerateSlug($locale)) {
                    Notification::make()->title(__('admin.slug.unchanged'))->warning()->send();

                    return;
                }

                $livewire->data['slug'] = $record->getTranslation('slug', $locale);

                Notification::make()->title(__('admin.slug.regenerated'))->success()->send();
            });
    }
}
