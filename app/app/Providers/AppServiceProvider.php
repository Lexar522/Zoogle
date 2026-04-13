<?php

namespace App\Providers;

use App\Models\Bundle;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CartDrawerService;
use Illuminate\Database\Eloquent\Relations\Relation;
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
        Relation::enforceMorphMap([
            'user' => User::class,
            'product' => Product::class,
            'product_variant' => ProductVariant::class,
            'bundle' => Bundle::class,
        ]);

        View::composer('layouts.shop', function ($view): void {
            $view->with('cartDrawerData', app(CartDrawerService::class)->forRequest(request()));
        });
    }
}
