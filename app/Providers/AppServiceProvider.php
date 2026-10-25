<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\CartItem;
use App\Services\CartService;
use Closure;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerLocalizedRouteMacro();
        $this->registerCartItemBinding();
        $this->configureAdminTables();

        // Inertia props are consumed directly by React components, not by an
        // external JSON:API client, so the extra {"data": {...}} envelope
        // JsonResource adds by default just means every single-resource
        // prop (e.g. new PageResource($page)) needs an unwanted ".data" in
        // the component. Paginated collections are unaffected — they keep
        // their data/links/meta shape, which Paginated<T> already expects.
        JsonResource::withoutWrapping();
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
