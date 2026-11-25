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
use Filament\Forms\Components\Repeater;
use Filament\Pages\BasePage;
use Filament\Resources\Pages\Page as ResourcePage;
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
        Section::configureUsing(fn (Section $section): Section => $section
            ->collapsible()
            ->collapsed(fn (Section $component, $livewire): bool => self::opensCollapsed($component, $livewire)));
        // Згорнутий рядок показує лише заголовок, тому без itemLabel він був би порожньою смужкою.
        Repeater::configureUsing(fn (Repeater $repeater): Repeater => $repeater
            ->collapsed(fn (Repeater $component): bool => $component->hasItemLabels()));

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

    /**
     * Сторінки налаштувань (головна, футер, інтеграції…) відкриваються згорнутими — так вони малюються швидше.
     * Форми товарів, замовлень і модалки лишаються розгорнутими: там заповнюють усі поля підряд.
     * Секцію без заголовка не згортаємо — її не було б чим розгорнути.
     */
    private static function opensCollapsed(Section $section, mixed $livewire): bool
    {
        return filled($section->getHeading())
            && $livewire instanceof BasePage
            && ! $livewire instanceof ResourcePage;
    }
}
