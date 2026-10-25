<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use RuntimeException;

/**
 * The whole file is unusable (not JSON, wrong schema version, no products).
 * The message is meant for the admin who uploaded it.
 */
class InvalidCatalogFileException extends RuntimeException {}
