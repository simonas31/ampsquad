<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the account area for customers who have verified their address; the
 * rest are sent to the page that asks them to. Runs after `auth:customer`.
 */
class EnsureCustomerIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user('customer')?->hasVerifiedEmail()) {
            return redirect()->route('customer.verification.notice');
        }

        return $next($request);
    }
}
