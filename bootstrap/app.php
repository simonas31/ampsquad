<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureCheckoutEnabled;
use App\Http\Middleware\EnsureCustomerIsVerified;
use App\Http\Middleware\EnsureShopEnabled;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetNoStoreHeader;
use App\Support\Seo\SeoData;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRoutes;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        // The payment provider's server posts here with no session and no
        // token; the message is authenticated by its signature instead.
        $middleware->preventRequestForgery(except: ['payments/*/callback']);

        // Guests on a customer page go to the customer login. Any other
        // route keeps what it had: the admin panel sends its own guests to
        // its own login.
        $middleware->redirectGuestsTo(
            fn (Request $request): ?string => $request->routeIs('account.*', 'customer.*') ? route('customer.login') : null,
        );
        // Signed-in customers who open the login or register page.
        $middleware->redirectUsersTo(fn (): string => route('account.dashboard'));

        $middleware->alias([
            'customer.verified' => EnsureCustomerIsVerified::class,
            'no-store' => SetNoStoreHeader::class,
            'shop.enabled' => EnsureShopEnabled::class,
            'checkout.enabled' => EnsureCheckoutEnabled::class,
            'localize' => LaravelLocalizationRoutes::class,
            'localizationRedirect' => LaravelLocalizationRedirectFilter::class,
            'localeSessionRedirect' => LocaleSessionRedirect::class,
            'localeViewPath' => LaravelLocalizationViewPath::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            $status = $response->getStatusCode();

            // The error carries a code, not a sentence: this runs before the
            // request's language is chosen, so the page words it itself.
            if ($status === 419) {
                return back()->withErrors(['page_expired' => 'page_expired']);
            }

            if (! app()->environment(['local', 'testing']) && in_array($status, [403, 404, 500, 503], true)) {
                $title = match ($status) {
                    403 => 'Forbidden',
                    404 => 'Page not found',
                    503 => 'Under maintenance',
                    default => 'Server error',
                };

                return Inertia::render('Error', [
                    'status' => $status,
                    'seo' => SeoData::make(pageTitle: $title, noindex: true),
                ])
                    ->toResponse($request)
                    ->setStatusCode($status);
            }

            return $response;
        });
    })->create();
