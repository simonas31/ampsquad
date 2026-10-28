<?php

declare(strict_types=1);

use App\Http\Controllers\CartController;
use App\Http\Controllers\CartItemController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PaymentCallbackController;
use App\Http\Controllers\PriceCalculatorController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\ShopController;
use Illuminate\Support\Facades\Route;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

// Outside the localized group, which ends in a "/{slug}" catch-all that would swallow it.
Route::get('/robots.txt', RobotsController::class)->name('robots');

// Where the payment provider's server posts. Outside the localized group too:
// it has no language, and no session or CSRF token either (see bootstrap/app.php).
// Not behind the shop or checkout switches: a payment started before they
// were switched off must still be confirmed.
Route::post('/payments/{provider}/callback', PaymentCallbackController::class)
    ->where('provider', '[a-z]+')
    ->middleware('throttle:payment-callback')
    ->name('payments.callback');

Route::localized(function (): void {
    Route::get('/', HomeController::class)->name('home');

    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects/{slug}', [ProjectController::class, 'show'])->name('projects.show');

    Route::get(LaravelLocalization::transRoute('routes.price_calculator'), [PriceCalculatorController::class, 'show'])
        ->name('price-calculator');

    Route::get('/contact', [ContactController::class, 'show'])->name('contact');
    Route::post('/contact', [ContactController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('contact.store');

    // The shop is switched on and off in the admin panel; while it is off
    // every route in this group answers 404. The URLs are the same in every
    // locale (like /projects): mcamara only translates single-segment paths,
    // which would break the language switcher on /shop/{slug}.
    Route::middleware('shop.enabled')->group(function (): void {
        Route::get('/shop', [ShopController::class, 'index'])->name('shop.index');
        Route::get('/shop/{slug}', [ShopController::class, 'show'])->name('shop.show');
        Route::get('/cart', [CartController::class, 'show'])->name('cart.show');

        Route::middleware('checkout.enabled')->group(function (): void {
            Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
            Route::post('/checkout', [CheckoutController::class, 'store'])
                ->middleware('throttle:checkout')
                ->name('checkout.store');
        });

        Route::middleware('throttle:60,1')->prefix('cart')->name('cart.')->group(function (): void {
            Route::post('/items', [CartItemController::class, 'store'])->name('items.store');
            Route::patch('/items/{cartItem}', [CartItemController::class, 'update'])
                ->whereNumber('cartItem')
                ->name('items.update');
            Route::delete('/items/{cartItem}', [CartItemController::class, 'destroy'])
                ->whereNumber('cartItem')
                ->name('items.destroy');
            Route::delete('/', [CartController::class, 'destroy'])->name('destroy');
        });
    });

    // An order's page is found by its uuid and is where the payment provider
    // sends the customer back to. It is not behind the shop or checkout
    // switches, so a customer who paid just before they went off still sees it.
    Route::get('/orders/{order:uuid}', [OrderController::class, 'show'])
        ->whereUuid('order')
        ->middleware('throttle:order-page')
        ->name('orders.show');

    // Catch-all for admin-managed static pages (about, privacy-policy,
    // terms-and-conditions, and whatever else gets added later) — must
    // stay last so the literal routes above take precedence.
    Route::get('/{slug}', [PageController::class, 'show'])->name('pages.show');
});
