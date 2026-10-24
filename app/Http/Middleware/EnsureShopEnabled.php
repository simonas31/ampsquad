<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Settings\ShopSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes every shop and cart route behave as if it did not exist while an
 * admin has the shop switched off, so nothing about it is indexable or
 * reachable by a stale link.
 */
class EnsureShopEnabled
{
    public function __construct(private readonly ShopSettings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->settings->enabled, 404);

        return $next($request);
    }
}
