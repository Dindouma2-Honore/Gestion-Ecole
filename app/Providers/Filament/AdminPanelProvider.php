<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Login;
use App\Filament\Pages\EditProfile;
use App\Filament\Support\AmbassadorsDesign;
use App\Filament\Support\ModuleNavigation;
use App\Http\Middleware\EnsureModuleAccess;
use App\Http\Middleware\ForcePasswordChangeMiddleware;
use App\Http\Middleware\SetUserLocale;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationBuilder;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->passwordReset()
            ->profile(EditProfile::class, isSimple: false)
            ->revealablePasswords()
            ->databaseNotifications()
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => Blade::render('<x-global-loading-overlay />')
            )
            ->brandName('Ambassadors Educational Complex')
            ->brandLogo(asset('images/logo.png'))
            ->brandLogoHeight('3rem')
            ->favicon(asset('images/logo.png'))
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->navigationGroups(AmbassadorsDesign::NAVIGATION_GROUPS)
            ->navigation(fn (): NavigationBuilder => ModuleNavigation::build())
            ->colors([
                'primary' => Color::hex('#1948bd'),
                'warning' => Color::hex('#d7aa35'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverResources(in: app_path('Modules/Socle/Filament/Resources'), for: 'App\Modules\Socle\Filament\Resources')
            ->discoverResources(in: app_path('Modules/Finances/Filament/Resources'), for: 'App\Modules\Finances\Filament\Resources')
            ->discoverResources(in: app_path('Modules/RH/Filament/Resources'), for: 'App\Modules\RH\Filament\Resources')
            ->discoverResources(in: app_path('Modules/Pedagogie/Filament/Resources'), for: 'App\Modules\Pedagogie\Filament\Resources')
            ->discoverResources(in: app_path('Modules/Scolarite/Filament/Resources'), for: 'App\Modules\Scolarite\Filament\Resources')
            ->discoverResources(in: app_path('Modules/Communication/Filament/Resources'), for: 'App\Modules\Communication\Filament\Resources')
            ->discoverResources(in: app_path('Modules/Assiduite/Filament/Resources'), for: 'App\Modules\Assiduite\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverPages(in: app_path('Modules/Socle/Filament/Pages'), for: 'App\Modules\Socle\Filament\Pages')
            ->discoverPages(in: app_path('Modules/RH/Filament/Pages'), for: 'App\Modules\RH\Filament\Pages')
            ->discoverPages(in: app_path('Modules/Finances/Filament/Pages'), for: 'App\Modules\Finances\Filament\Pages')
            ->discoverPages(in: app_path('Modules/Scolarite/Filament/Pages'), for: 'App\Modules\Scolarite\Filament\Pages')
            ->discoverPages(in: app_path('Modules/Pedagogie/Filament/Pages'), for: 'App\Modules\Pedagogie\Filament\Pages')
            ->discoverPages(in: app_path('Modules/Assiduite/Filament/Pages'), for: 'App\Modules\Assiduite\Filament\Pages')
            ->discoverResources(in: app_path('Modules/VieScolaire/Filament/Resources'), for: 'App\Modules\VieScolaire\Filament\Resources')
            ->discoverResources(in: app_path('Modules/Logistique/Filament/Resources'), for: 'App\Modules\Logistique\Filament\Resources')
            ->discoverPages(in: app_path('Modules/Logistique/Filament/Pages'), for: 'App\Modules\Logistique\Filament\Pages')
            ->discoverPages(in: app_path('Modules/VieScolaire/Filament/Pages'), for: 'App\Modules\VieScolaire\Filament\Pages')
            ->discoverPages(in: app_path('Modules/Communication/Filament/Pages'), for: 'App\Modules\Communication\Filament\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->discoverWidgets(in: app_path('Modules/Communication/Filament/Widgets'), for: 'App\Modules\Communication\Filament\Widgets')
            ->discoverWidgets(in: app_path('Modules/Assiduite/Filament/Widgets'), for: 'App\Modules\Assiduite\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
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
            ->authMiddleware([
                Authenticate::class,
                SetUserLocale::class,
                ForcePasswordChangeMiddleware::class,
                EnsureModuleAccess::class,
            ]);
    }
}
