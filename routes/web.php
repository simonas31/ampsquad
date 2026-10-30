<?php

declare(strict_types=1);

use App\Http\Controllers\Account\DashboardController;
use App\Http\Controllers\Account\DeleteAccountController;
use App\Http\Controllers\Account\EmailController;
use App\Http\Controllers\Account\PasswordController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Account\SecurityController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\VerifyEmailController;
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

// The links in the mails that verify a customer's address and confirm a new
// one. Outside the localized group too: a mail is built by the queue worker,
// which has no visitor and so no language, and the signature covers the exact
// URL, so the links have no language prefix. The controllers send the
// customer on in theirs.
Route::middleware(['signed', 'throttle:customer-verification'])->whereNumber('id')->group(function (): void {
    Route::get('/account/verify-email/{id}/{hash}', [VerifyEmailController::class, 'verify'])
        ->name('customer.verification.verify');
    Route::get('/account/email/confirm/{id}/{hash}', [EmailController::class, 'confirm'])
        ->name('customer.email.confirm');
});

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

    // Customer accounts. Not behind the shop switch: a customer must still be
    // able to sign in and see their orders while the shop is off. Every page
    // here is about one person, so none is stored by a browser or a proxy.
    Route::middleware('no-store')->group(function (): void {
        Route::middleware('guest:customer')->group(function (): void {
            Route::get('/login', [LoginController::class, 'show'])->name('customer.login');
            Route::post('/login', [LoginController::class, 'store'])
                ->middleware('throttle:customer-login')
                ->name('customer.login.store');

            Route::get('/register', [RegisterController::class, 'show'])->name('customer.register');
            Route::post('/register', [RegisterController::class, 'store'])
                ->middleware('throttle:customer-register')
                ->name('customer.register.store');

            Route::get('/forgot-password', [ForgotPasswordController::class, 'show'])->name('customer.password.request');
            Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])
                ->middleware('throttle:customer-password-reset')
                ->name('customer.password.email');

            Route::get('/reset-password/{token}', [ResetPasswordController::class, 'show'])->name('customer.password.reset');
            Route::post('/reset-password', [ResetPasswordController::class, 'store'])
                ->middleware('throttle:customer-password-reset')
                ->name('customer.password.store');
        });

        Route::middleware(['auth:customer', 'customer.session'])->group(function (): void {
            Route::post('/logout', LogoutController::class)->name('customer.logout');

            Route::get('/account/verify-email', [VerifyEmailController::class, 'notice'])
                ->name('customer.verification.notice');
            Route::post('/account/verify-email/resend', [VerifyEmailController::class, 'resend'])
                ->middleware('throttle:customer-verification')
                ->name('customer.verification.send');

            Route::middleware('customer.verified')->group(function (): void {
                Route::get('/account', DashboardController::class)->name('account.dashboard');

                Route::get('/account/profile', [ProfileController::class, 'show'])->name('account.profile');
                Route::patch('/account/profile', [ProfileController::class, 'update'])->name('account.profile.update');

                Route::get('/account/security', SecurityController::class)->name('account.security');
                Route::middleware('throttle:customer-account')->group(function (): void {
                    Route::post('/account/email', [EmailController::class, 'update'])->name('account.email.update');
                    Route::put('/account/password', [PasswordController::class, 'update'])->name('account.password.update');
                    Route::delete('/account', DeleteAccountController::class)->name('account.destroy');
                });
            });
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
