<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use JsonException;

/**
 * Reads a catalog file and checks its envelope: valid JSON, a supported
 * schema version, and a non-empty list of products. The products themselves
 * are checked one by one by CatalogRowParser, so one bad product never
 * rejects the file.
 *
 * The file is decoded in one go. Even a catalog of tens of thousands of
 * products stays within a few tens of megabytes, so MAX_BYTES is the
 * ceiling that keeps a queue worker's memory predictable.
 */
final class CatalogFileReader
{
    public const int SCHEMA_VERSION = 1;

    public const int MAX_BYTES = 50 * 1024 * 1024;

    /**
     * @return list<mixed>
     *
     * @throws InvalidCatalogFileException
     */
    public function read(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidCatalogFileException(__('admin.catalog_import.errors.file_missing'));
        }

        if (filesize($path) > self::MAX_BYTES) {
            throw new InvalidCatalogFileException(__('admin.catalog_import.errors.file_too_large', [
                'max' => self::MAX_BYTES / 1024 / 1024,
            ]));
        }

        $contents = (string) file_get_contents($path);

        // Some editors and export tools prepend a byte order mark.
        if (str_starts_with($contents, "\xEF\xBB\xBF")) {
            $contents = substr($contents, 3);
        }

        try {
            $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidCatalogFileException(__('admin.catalog_import.errors.invalid_json', [
                'error' => $exception->getMessage(),
            ]), previous: $exception);
        }

        if (! is_array($data) || array_is_list($data)) {
            throw new InvalidCatalogFileException(__('admin.catalog_import.errors.not_an_object'));
        }

        if (($data['schema_version'] ?? null) !== self::SCHEMA_VERSION) {
            throw new InvalidCatalogFileException(__('admin.catalog_import.errors.unsupported_version', [
                'version' => self::SCHEMA_VERSION,
            ]));
        }

        $products = $data['products'] ?? null;

        if (! is_array($products) || ! array_is_list($products)) {
            throw new InvalidCatalogFileException(__('admin.catalog_import.errors.products_not_a_list'));
        }

        if ($products === []) {
            throw new InvalidCatalogFileException(__('admin.catalog_import.errors.no_products'));
        }

        return $products;
    }
}
