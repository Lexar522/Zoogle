<?php

namespace App\Providers\Filament;

use App\Filament\Admin\Resources\Products\Pages\ListProducts;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Tables\View\TablesRenderHook;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->default()
            ->path('admin')
            ->authGuard('web')
            ->login()
            ->profile()
            ->brandName('ZOOGLE')
            ->brandLogo(asset('images/zoogle-logo.png'))
            ->brandLogoHeight('2rem')
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => '<style>img.fi-logo{background:transparent!important;background-color:transparent!important;}</style>'
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => view('filament.hooks.listing-photos-upload-layout')->render()
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                function (): string {
                    $path = resource_path('css/filament/list-products-table-toolbar.css');

                    return is_file($path) ? '<style>'.file_get_contents($path).'</style>' : '';
                }
            )
            ->renderHook(
                TablesRenderHook::TOOLBAR_SEARCH_BEFORE,
                fn (): string => view('filament.admin.resources.products.list-products-table-search-row-start')->render(),
                scopes: ListProducts::class,
            )
            ->renderHook(
                TablesRenderHook::TOOLBAR_SEARCH_AFTER,
                fn (): string => view('filament.admin.resources.products.list-products-table-search-row-end')->render(),
                scopes: ListProducts::class,
            )
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\Filament\Admin\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\Filament\Admin\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\Filament\Admin\Widgets')
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
            ]);
    }
}
