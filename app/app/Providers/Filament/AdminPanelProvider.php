<?php

namespace App\Providers\Filament;

use App\Filament\Admin\Pages\Dashboard;
use App\Filament\Admin\Resources\Orders\Widgets\OrdersSummaryWidget;
use App\Filament\Admin\Resources\Products\Pages\ListProducts;
use App\Filament\Admin\Widgets\AdminHomeWidget;
use App\Filament\Admin\Widgets\QuestionsAttentionWidget;
use App\Filament\Admin\Widgets\ReviewsAttentionWidget;
use App\Filament\Admin\Widgets\SponsorshipAttentionWidget;
use App\Http\Middleware\AuthenticateAdminPanel;
use App\Http\Middleware\SetNoReferrerPolicy;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Tables\View\TablesRenderHook;
use Filament\View\PanelsRenderHook;
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
            // Дзвіночок: нові питання покупців до товарів
            ->databaseNotifications()
            ->databaseNotificationsPolling('60s')
            ->brandName('ZOOGLE')
            ->favicon(asset('favicon.png'))
            ->brandLogo(asset('images/zoogle-logo-new.png'))
            ->brandLogoHeight('2rem')
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => '<style>img.fi-logo{background:transparent!important;background-color:transparent!important;}</style>'
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => view('filament.hooks.apple-mobile-meta')->render()
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
                PanelsRenderHook::HEAD_END,
                function (): string {
                    $path = resource_path('css/filament/order-items-table.css');

                    return is_file($path) ? '<style>'.file_get_contents($path).'</style>' : '';
                }
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                function (): string {
                    $css = '';
                    foreach (['apple', 'orders'] as $name) {
                        $path = resource_path('css/filament/'.$name.'.css');
                        $css .= is_file($path) ? (string) file_get_contents($path) : '';
                    }

                    return '<style>'.$css.'</style>';
                }
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                function (): string {
                    $path = resource_path('css/filament/rich-editor-reading.css');
                    $css = is_file($path) ? (string) file_get_contents($path) : '';

                    return '<link rel="preconnect" href="https://fonts.googleapis.com">'
                        .'<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
                        .'<link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:ital,wght@0,400;0,600;0,700;1,400;1,600&display=swap" rel="stylesheet" media="print" onload="this.media=\'all\'">'
                        .($css !== '' ? '<style>'.$css.'</style>' : '');
                }
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_FOOTER,
                fn (): string => view('filament.hooks.site-release')->render()
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
            ->spa()
            ->navigationGroups(['Продажі', 'Каталог', 'Маркетинг', 'Довідники', 'Налаштування', 'Система'])
            ->colors([
                'primary' => Color::hex('#007aff'),
            ])
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\Filament\Admin\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\Filament\Admin\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\Filament\Admin\Widgets')
            ->widgets([
                AdminHomeWidget::class,
                SponsorshipAttentionWidget::class,
                QuestionsAttentionWidget::class,
                ReviewsAttentionWidget::class,
                OrdersSummaryWidget::class,
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
                SetNoReferrerPolicy::class,
            ])
            ->authMiddleware([
                AuthenticateAdminPanel::class,
            ]);
    }
}
