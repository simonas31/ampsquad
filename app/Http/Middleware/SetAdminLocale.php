<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * The admin panel's own UI locale (nav labels, field labels, Filament's
 * bundled chrome translations) is independent of the public site's
 * URL-prefix locale — /admin* is excluded from mcamara/laravel-localization
 * entirely, so it needs its own session-based switch instead.
 */
class SetAdminLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale(session('admin_locale', config('app.locale')));

        return $next($request);
    }
}
