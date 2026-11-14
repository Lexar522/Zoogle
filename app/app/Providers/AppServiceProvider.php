<?php

namespace App\Providers;

use App\Models\Bundle;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CartDrawerService;
use App\Support\ShopFooterViewData;
use App\Support\ShopHeaderContacts;
use App\Support\StorefrontViewCache;
use Filament\Pages\BasePage;
use Filament\Schemas\Components\Section;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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
        TrustProxies::at(array_values(array_filter([
            ...(array) config('shop.trusted_proxies', []),
            request()->server('SERVER_ADDR'),
        ])));

        // На телефоні таблиці адмінки йдуть картками, а «Зберегти» завжди під рукою.
        Table::configureUsing(fn (Table $table): Table => $table->stackedOnMobile());
        BasePage::stickyFormActions();
        Section::configureUsing(fn (Section $section): Section => $section->collapsible());

        Relation::enforceMorphMap([
            'user' => User::class,
            'product' => Product::class,
            'product_variant' => ProductVariant::class,
            'bundle' => Bundle::class,
        ]);

        StorefrontViewCache::registerInvalidation();

        View::composer('layouts.shop', function ($view): void {
            $view->with('cartDrawerData', app(CartDrawerService::class)->forRequest(request()));
            $shopHeaderContactItems = ShopHeaderContacts::storefrontItems();
            $view->with('shopHeaderContactItems', $shopHeaderContactItems);
            $view->with('hasShopHeaderContacts', $shopHeaderContactItems !== []);
            $view->with('shopFooter', ShopFooterViewData::forRequest());
        });
    }
}
