<?php

declare(strict_types=1);

namespace App\Support\Seo;

final readonly class Breadcrumbs
{
    /**
     * @param  array<int, array{label: string, url: string|null}>  $items
     */
    public function __construct(public array $items) {}

    /**
     * @param  array<int, array{label: string, url: string|null}>  $items
     */
    public static function make(array $items): self
    {
        return new self($items);
    }

    /**
     * @return array<int, array{label: string, url: string|null}>
     */
    public function toArray(): array
    {
        return $this->items;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonLd(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($this->items)
                ->values()
                ->map(fn (array $item, int $index) => array_filter([
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $item['label'],
                    'item' => $item['url'],
                ]))
                ->all(),
        ];
    }
}
