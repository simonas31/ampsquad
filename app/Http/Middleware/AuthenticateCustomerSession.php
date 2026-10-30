<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs a customer out of every browser but the one that changed their
 * password. Each session keeps a stamp of the password it signed in with; a
 * session whose stamp no longer matches the account's password is ended.
 *
 * Laravel's own AuthenticateSession does this for the default guard only,
 * which here is the admin panel's, and throws the whole session away, which
 * would sign a person working in both out of both.
 */
class AuthenticateCustomerSession
{
    private const string KEY = 'customer_password_stamp';

    /**
     * Stamps the session when a customer signs in or registers, so that a
     * password changed before their next request still ends it.
     */
    public static function stamp(Request $request): void
    {
        $customer = Auth::guard('customer')->user();

        if ($customer !== null) {
            $request->session()->put(self::KEY, self::stampOf($customer->getAuthPassword()));
        }
    }

    /**
     * @throws AuthenticationException
     */
    public function handle(Request $request, Closure $next): Response
    {
        $customer = Auth::guard('customer')->user();

        if ($customer === null || ! $request->hasSession()) {
            return $next($request);
        }

        $current = self::stampOf($customer->getAuthPassword());
        $stamped = $request->session()->get(self::KEY);

        if ($stamped === null) {
            $request->session()->put(self::KEY, $current);
        } elseif (! hash_equals($current, (string) $stamped)) {
            Auth::guard('customer')->logoutCurrentDevice();
            $request->session()->forget(self::KEY);

            throw new AuthenticationException('Unauthenticated.', ['customer']);
        }

        return $next($request);
    }

    /**
     * Not the hash itself: the session may be kept where a copy would be read.
     */
    private static function stampOf(string $passwordHash): string
    {
        return hash_hmac('sha256', $passwordHash, (string) config('app.key'));
    }
}
