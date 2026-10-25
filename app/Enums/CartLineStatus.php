<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a cart line is worth right now, which can differ from what it held
 * when the product was added: stock moves and products are taken off sale.
 * The values are what the client reads.
 */
enum CartLineStatus: string
{
    /** The whole quantity can be bought. */
    case Available = 'available';

    /** Less is in stock than the line holds; only what is left is counted. */
    case Reduced = 'reduced';

    /** The product is sold out, switched off or deleted; nothing is counted. */
    case Unavailable = 'unavailable';

    /**
     * Whether any units of the line count towards the totals.
     */
    public function isCounted(): bool
    {
        return $this !== self::Unavailable;
    }
}
