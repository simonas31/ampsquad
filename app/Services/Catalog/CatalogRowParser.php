<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Product;
use Closure;
use Illuminate\Support\Facades\Validator;

/**
 * Turns one raw product from a catalog file into a CatalogRow, or says
 * everything that is wrong with it. This is the contract in
 * resources/catalog/catalog.template.json: text is plain (no HTML), the price
 * is a decimal string (floats would round), and stock is a whole number.
 * Keys it does not know are ignored.
 */
final class CatalogRowParser
{
    public const int MAX_SKU_LENGTH = 64;

    public const int MAX_NAME_LENGTH = 255;

    public const int MAX_EXCERPT_LENGTH = 255;

    public const int MAX_DESCRIPTION_LENGTH = 5000;

    public const int MAX_CATEGORY_DEPTH = 8;

    public const int MAX_STOCK = 100_000_000;

    /**
     * @var list<string>
     */
    private const array TEXT_KEYS = [
        'sku', 'name', 'excerpt', 'description', 'price', 'image_url', 'manufacturer', 'ean',
    ];

    /**
     * @throws InvalidCatalogRowException
     */
    public function parse(mixed $row): CatalogRow
    {
        if (! is_array($row) || ($row !== [] && array_is_list($row))) {
            throw new InvalidCatalogRowException([__('admin.catalog_import.errors.row_not_an_object')]);
        }

        $validator = Validator::make($this->trimmed($row), $this->rules(), [
            'price.regex' => __('admin.catalog_import.errors.price_format'),
            'ean.regex' => __('admin.catalog_import.errors.ean_format'),
        ]);

        if ($validator->fails()) {
            throw new InvalidCatalogRowException(array_values(array_unique($validator->errors()->all())));
        }

        $data = $validator->validated();

        return new CatalogRow(
            sku: $data['sku'],
            name: $data['name'],
            categoryPath: $data['category'] ?? [],
            excerpt: $this->blankToNull($data['excerpt'] ?? null),
            description: $this->blankToNull($data['description'] ?? null),
            priceCents: self::priceToCents($data['price']),
            stock: $data['stock'],
            imageUrl: $this->blankToNull($data['image_url'] ?? null),
            manufacturer: $this->blankToNull($data['manufacturer'] ?? null),
            ean: $this->blankToNull($data['ean'] ?? null),
        );
    }

    /**
     * "19.99" -> 1999 without going through a float. The input has already
     * matched the price pattern: digits, optionally a dot and 1-2 decimals.
     */
    public static function priceToCents(string $price): int
    {
        [$whole, $fraction] = array_pad(explode('.', $price, 2), 2, '');

        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'sku' => ['bail', 'required', 'string', 'max:'.self::MAX_SKU_LENGTH],
            'name' => ['bail', 'required', 'string', 'max:'.self::MAX_NAME_LENGTH],
            'category' => ['nullable', 'array', 'list', 'max:'.self::MAX_CATEGORY_DEPTH],
            'category.*' => ['bail', 'required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:'.self::MAX_EXCERPT_LENGTH],
            'description' => ['nullable', 'string', 'max:'.self::MAX_DESCRIPTION_LENGTH],
            'price' => ['bail', 'required', 'string', 'regex:/^\d{1,6}(\.\d{1,2})?$/', $this->priceIsInRange(...)],
            'stock' => ['required', 'integer:strict', 'min:0', 'max:'.self::MAX_STOCK],
            'image_url' => ['nullable', 'string', 'max:500', 'url:http,https'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'ean' => ['nullable', 'string', 'regex:/^\d{8,14}$/'],
        ];
    }

    private function priceIsInRange(string $attribute, mixed $value, Closure $fail): void
    {
        $cents = self::priceToCents((string) $value);

        if ($cents < 1 || $cents > Product::MAX_PRICE_CENTS) {
            $fail(__('admin.catalog_import.errors.price_range', [
                'max' => number_format(Product::MAX_PRICE_CENTS / 100, 2, '.', ''),
            ]));
        }
    }

    /**
     * Surrounding whitespace is noise in every text field, and a name of
     * only spaces should count as missing.
     *
     * @param  array<array-key, mixed>  $row
     * @return array<array-key, mixed>
     */
    private function trimmed(array $row): array
    {
        foreach (self::TEXT_KEYS as $key) {
            if (isset($row[$key]) && is_string($row[$key])) {
                $row[$key] = trim($row[$key]);
            }
        }

        if (isset($row['category']) && is_array($row['category'])) {
            $row['category'] = array_map(
                fn (mixed $segment): mixed => is_string($segment) ? trim($segment) : $segment,
                $row['category'],
            );
        }

        return $row;
    }

    private function blankToNull(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $value;
    }
}
