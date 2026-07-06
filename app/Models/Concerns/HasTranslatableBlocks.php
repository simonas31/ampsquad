<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Support\Facades\App;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

/**
 * Blocks are stored as one shared array (same count/order/type across
 * locales) where only leaf text fields carry both languages directly, e.g.
 * a heading block's payload is {"text": {"lt": "...", "en": "..."}}. This
 * sidesteps a documented bug where Filament's Builder field can reorder
 * blocks when combined with a locale-switching translatable plugin, and
 * keeps LT/EN structurally identical — a feature for a bilingual brochure
 * site (same photo/section order in both languages), not a limitation.
 *
 * Filament's form binds directly to the raw `blocks` attribute, showing
 * both locale inputs side by side per field. This trait is only for
 * resolving that raw structure down to one locale's strings for display.
 */
trait HasTranslatableBlocks
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function resolvedBlocks(?string $locale = null): array
    {
        $locale ??= App::getLocale();
        $fallback = (string) config('app.fallback_locale');

        return collect($this->blocks ?? [])
            ->map(fn (array $block) => $this->resolveBlockValue($block, $locale, $fallback))
            ->all();
    }

    private function resolveBlockValue(mixed $value, string $locale, string $fallback): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if ($this->isLocaleMap($value)) {
            return $value[$locale] ?? $value[$fallback] ?? null;
        }

        return collect($value)
            ->map(fn (mixed $item) => $this->resolveBlockValue($item, $locale, $fallback))
            ->all();
    }

    /**
     * A translatable leaf is an array keyed exactly by the supported
     * locale codes (e.g. ["lt" => ..., "en" => ...]) — this distinguishes
     * it from a plain list (e.g. media_ids: [1, 2, 3]) or a non-translatable
     * nested object, neither of which should be collapsed to one locale.
     *
     * @param  array<array-key, mixed>  $value
     */
    private function isLocaleMap(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        $supportedLocales = array_keys(LaravelLocalization::getSupportedLocales());
        $keys = array_keys($value);

        sort($supportedLocales);
        sort($keys);

        return $keys === $supportedLocales;
    }
}
