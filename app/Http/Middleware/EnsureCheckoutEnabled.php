<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Settings\ShopSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes the checkout routes behave as if they did not exist until an admin
 * has switched online checkout on. The payment provider's callback and the
 * order page are not behind this: a payment started before checkout was
 * switched off must still be confirmed and shown.
 */
class EnsureCheckoutEnabled
{
    public function __construct(private readonly ShopSettings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->settings->checkoutEnabled, 404);

        return $next($request);
    }
}
