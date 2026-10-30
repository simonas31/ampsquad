<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\SlugRedirect;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

/**
 * Derives the translatable `slug` attribute from another translatable
 * attribute (`title` by default) on save, so slugs are never typed in by a
 * user or sent from the client. Requires Spatie's HasTranslations with
 * `slug` listed in `$translatable`.
 *
 * Per locale, a slug is generated when it is missing, or when the source text
 * changed and the record is not locked (see slugIsLocked()). A slug assigned
 * in code during the same save (a factory state, regenerateSlug()) is kept.
 * Collisions with other records, retired slugs and reserved words get a
 * numeric suffix.
 *
 * Once a record is locked its URL is indexed, so changing a slug through
 * regenerateSlug() keeps the old one in `slug_redirects` for a 301.
 */
trait HasSlug
{
    /**
     * Slug changes collected while saving, persisted once the row exists.
     *
     * @var array<string, array{new: string, retired: string|null}>
     */
    protected array $pendingSlugChanges = [];

    public static function bootHasSlug(): void
    {
        static::saving(function (Model $model): void {
            $model->syncSlugTranslations();
        });

        static::saved(function (Model $model): void {
            $model->persistSlugChanges();
        });

        static::deleted(function (Model $model): void {
            if (! method_exists($model, 'isForceDeleting') || $model->isForceDeleting()) {
                $model->slugRedirects()->delete();
            }
        });
    }

    /**
     * @return MorphMany<SlugRedirect, $this>
     */
    public function slugRedirects(): MorphMany
    {
        return $this->morphMany(SlugRedirect::class, 'redirectable');
    }

    /**
     * Records that used to have $slug in the given (default: current) locale.
     */
    public function scopeMovedFromSlug(Builder $query, string $slug, ?string $locale = null): void
    {
        $query->whereHas('slugRedirects', fn (Builder $redirects) => $redirects
            ->where('locale', $locale ?? app()->getLocale())
            ->where('old_slug', $slug));
    }

    /**
     * Records that have a slug in the current locale, so they have a URL to
     * link to. A record written in one language only has none in the other.
     *
     * @param  Builder<static>  $query
     */
    public function scopeWithSlugInCurrentLocale(Builder $query): void
    {
        $column = 'slug_'.app()->getLocale();

        $query->whereNotNull($column)->where($column, '!=', '');
    }

    /**
     * Rebuilds the slug for one locale from the saved source text, keeping
     * the old slug as a redirect when the record is locked. Returns whether
     * the slug changed.
     */
    public function regenerateSlug(string $locale): bool
    {
        $base = Str::slug((string) $this->getTranslation($this->slugSourceAttribute(), $locale, false));

        if ($base === '') {
            return false;
        }

        $slug = $this->uniqueSlug($base, $locale);

        if ($slug === $this->getTranslation('slug', $locale, false)) {
            return false;
        }

        $this->setTranslation('slug', $locale, $slug);
        $this->save();

        return true;
    }

    /**
     * Name of the translatable attribute the slug is generated from.
     */
    protected function slugSourceAttribute(): string
    {
        return 'title';
    }

    /**
     * Whether the record's current slugs are live URLs that must stay put.
     * A locked record keeps its slugs when the source text changes. They
     * only change through regenerateSlug(), which leaves a redirect.
     */
    protected function slugIsLocked(): bool
    {
        return false;
    }

    /**
     * Slugs the record must never get because a literal route owns them.
     *
     * @return list<string>
     */
    protected function reservedSlugs(string $locale): array
    {
        return [];
    }

    protected function syncSlugTranslations(): void
    {
        $source = $this->slugSourceAttribute();
        $originalSources = $this->originalTranslations($source);
        $originalSlugs = $this->originalTranslations('slug');
        $currentSlugs = $this->getTranslations('slug');
        $isLocked = $this->exists && $this->slugIsLocked();

        foreach ($this->getTranslations($source) as $locale => $text) {
            $base = Str::slug((string) $text);

            if ($base === '') {
                continue;
            }

            $currentSlug = $currentSlugs[$locale] ?? null;
            $hasSlug = is_string($currentSlug) && $currentSlug !== '';
            $originalSlug = $originalSlugs[$locale] ?? null;

            if ($hasSlug && $currentSlug !== $originalSlug) {
                $newSlug = $currentSlug;
            } elseif ($hasSlug && ($isLocked || ($originalSources[$locale] ?? null) === $text)) {
                continue;
            } else {
                $newSlug = $this->uniqueSlug($base, $locale);
                $this->setTranslation('slug', $locale, $newSlug);
            }

            $this->pendingSlugChanges[$locale] = [
                'new' => $newSlug,
                'retired' => $isLocked && is_string($originalSlug) && $originalSlug !== '' && $originalSlug !== $newSlug
                    ? $originalSlug
                    : null,
            ];
        }
    }

    protected function persistSlugChanges(): void
    {
        foreach ($this->pendingSlugChanges as $locale => $change) {
            // A slug coming back into use must not also redirect away.
            $this->slugRedirects()->where('locale', $locale)->where('old_slug', $change['new'])->delete();

            if ($change['retired'] !== null) {
                $this->slugRedirects()->updateOrCreate(['locale' => $locale, 'old_slug' => $change['retired']]);
            }
        }

        $this->pendingSlugChanges = [];
    }

    protected function uniqueSlug(string $base, string $locale): string
    {
        $slug = $base;
        $suffix = 2;

        while ($this->slugIsTaken($slug, $locale)) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    protected function slugIsTaken(string $slug, string $locale): bool
    {
        if (in_array($slug, $this->reservedSlugs($locale), true)) {
            return true;
        }

        $usedByRecord = static::query()
            ->withoutGlobalScopes()
            ->where('slug->'.$locale, $slug)
            ->when($this->exists, fn (Builder $query) => $query->whereKeyNot($this->getKey()))
            ->exists();

        if ($usedByRecord) {
            return true;
        }

        return SlugRedirect::query()
            ->where('redirectable_type', $this->getMorphClass())
            ->where('locale', $locale)
            ->where('old_slug', $slug)
            ->when($this->exists, fn (Builder $query) => $query->where('redirectable_id', '!=', $this->getKey()))
            ->exists();
    }

    /**
     * @return array<string, string|null>
     */
    private function originalTranslations(string $attribute): array
    {
        $raw = $this->getRawOriginal($attribute);

        return is_string($raw) ? (json_decode($raw, true) ?: []) : [];
    }
}
