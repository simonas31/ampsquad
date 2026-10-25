<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use RuntimeException;

/**
 * A single product in the file is unusable. The import skips it and carries on.
 */
class InvalidCatalogRowException extends RuntimeException
{
    /**
     * @param  list<string>  $messages
     */
    public function __construct(public readonly array $messages)
    {
        parent::__construct(implode(' ', $messages));
    }
}
