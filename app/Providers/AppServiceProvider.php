<?php

declare(strict_types=1);

namespace App\Providers;

use Closure;
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

        // Inertia props are consumed directly by Vue components, not by an
        // external JSON:API client, so the extra {"data": {...}} envelope
        // JsonResource adds by default just means every single-resource
        // prop (e.g. new PageResource($page)) needs an unwanted ".data" in
        // the template. Paginated collections are unaffected — they keep
        // their data/links/meta shape, which Paginated<T> already expects.
        JsonResource::withoutWrapping();
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
