<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Page;

/**
 * Where an admin-managed page is, for the places that must link to one the
 * shop is expected to have made, such as the privacy policy.
 */
final class PageUrls
{
    /**
     * Where the page is, or null if the shop has not made it. The page is
     * found by its key or, as admins make pages by their title and slug, by
     * any of the slugs it is known by in either language, so it does not
     * matter which of the two was set. A page with no slug in the current
     * language has no URL there, so it is left out rather than linked.
     */
    public static function find(string $key, string ...$slugs): ?string
    {
        $page = Page::query()
            ->withSlugInCurrentLocale()
            ->where(fn ($query) => $query
                ->where('key', $key)
                ->when($slugs !== [], fn ($query) => $query->orWhereIn('slug_lt', $slugs)->orWhereIn('slug_en', $slugs)))
            ->first();

        return $page === null ? null : route('pages.show', ['slug' => $page->slug], absolute: false);
    }

    public static function privacyPolicy(): ?string
    {
        return self::find('privacy-policy', 'privatumo-politika', 'privacy-policy');
    }
}
