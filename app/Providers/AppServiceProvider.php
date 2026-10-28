<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\CartItem;
use App\Services\CartService;
use App\Services\Payment\FakeGateway;
use App\Services\Payment\OpayGateway;
use App\Services\Payment\PaymentGateway;
use Closure;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PaymentGateway::class, fn (): PaymentGateway => match (config('shop.payment_gateway')) {
            OpayGateway::NAME => new OpayGateway((array) config('services.opay')),
            FakeGateway::NAME => $this->app->isProduction()
                ? throw new RuntimeException('The fake payment gateway cannot run in production. Set PAYMENT_GATEWAY=opay.')
                : new FakeGateway,
            default => throw new RuntimeException('Unknown payment gateway: '.config('shop.payment_gateway')),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerLocalizedRouteMacro();
        $this->registerCartItemBinding();
        $this->configureAdminTables();
        $this->configureRateLimiting();

        // Inertia props are consumed directly by React components, not by an
        // external JSON:API client, so the extra {"data": {...}} envelope
        // JsonResource adds by default just means every single-resource
        // prop (e.g. new PageResource($page)) needs an unwanted ".data" in
        // the component. Paginated collections are unaffected — they keep
        // their data/links/meta shape, which Paginated<T> already expects.
        JsonResource::withoutWrapping();
    }

    /**
     * Named limiters for the checkout routes. A plain `throttle:10,1` is keyed
     * by the visitor's IP alone, not by route, so it would share one allowance
     * with every other plain throttle (the cart's 60 a minute, the contact
     * form's 5): ten cart edits would leave nothing for checkout. A named
     * limiter has its own.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('checkout', fn (Request $request): Limit => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('order-page', fn (Request $request): Limit => Limit::perMinute(60)->by($request->ip()));
        RateLimiter::for('payment-callback', fn (Request $request): Limit => Limit::perMinute(120)->by($request->ip()));
    }

    /**
     * Every admin table shows its filters in the open above the rows and
     * applies them as they are chosen, instead of tucking them into a
     * dropdown behind a button with an "Apply" step. With thousands of
     * records the filters are the way in, so they should not be hidden.
     */
    private function configureAdminTables(): void
    {
        Table::configureUsing(fn (Table $table): Table => $table
            ->filtersLayout(FiltersLayout::AboveContent)
            ->deferFilters(false));
    }

    /**
     * {cartItem} only ever resolves a line of the visitor's own cart, so a
     * line id belonging to anyone else is a plain 404 (no existence leak),
     * and controllers need no ownership check of their own.
     */
    private function registerCartItemBinding(): void
    {
        Route::bind('cartItem', function (string $value): CartItem {
            $cart = app(CartService::class)->current(request());

            abort_if($cart === null, 404);

            return $cart->items()->whereKey($value)->firstOrFail();
        });
    }

    /**
     * Wraps mcamara/laravel-localization's group setup behind a first-party
     * macro, so route files depend on our own API rather than the package's
     * directly — swapping the underlying locale-routing implementation later
     * only touches this one place.
     */
    private function registerLocalizedRouteMacro(): void
    {
        Route::macro('localized', function (Closure $routes): void {
            Route::group([
                'prefix' => LaravelLocalization::setLocale(),
                'middleware' => ['localize', 'localizationRedirect'],
            ], $routes);
        });
    }
}
