<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Http\Middleware\SetAdminLocale;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use LaraZeus\SpatieTranslatable\SpatieTranslatablePlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->plugin(
                SpatieTranslatablePlugin::make()
                    ->defaultLocales(['lt', 'en'])
                    ->persist(),
            )
            ->navigationGroups([
                // Array keys are the stable, locale-independent identifiers
                // resources' $navigationGroup = 'Content' etc. are matched
                // against; only the label (this closure) is translated. If
                // these were registered as a plain sequential array of
                // NavigationGroup objects, Filament would only ever match a
                // resource's group by comparing against getLabel() — which
                // fails the moment the label isn't in English anymore.
                'Content' => NavigationGroup::make(fn () => __('admin.nav_groups.content')),
                'Communication' => NavigationGroup::make(fn () => __('admin.nav_groups.communication')),
                'Settings' => NavigationGroup::make(fn () => __('admin.nav_groups.settings')),
            ])
            ->userMenuItems([
                Action::make('locale')
                    ->label(fn () => __('admin.locale_switcher.'.(app()->getLocale() === 'lt' ? 'en' : 'lt')))
                    ->icon(Heroicon::OutlinedLanguage)
                    ->action(function () {
                        session(['admin_locale' => app()->getLocale() === 'lt' ? 'en' : 'lt']);

                        return redirect(request()->header('Referer') ?? route('filament.admin.pages.dashboard'));
                    }),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->middleware([
                SetAdminLocale::class,
            ], isPersistent: true)
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
