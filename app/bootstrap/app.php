<?php

use App\Http\Middleware\AllowAccountOrdersWithoutGoogle;
use App\Http\Middleware\ForceUrlFromIncomingRequest;
use App\Http\Middleware\RecordShopAnalytics;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetNoReferrerPolicy;
use App\Http\Middleware\SetShopLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Підписані URL (Livewire upload/preview) мають збігатися зі схемою/host за проксі (HTTPS).
        // X-Forwarded-Host не довіряємо: інакше клієнт підставляє свій домен у посилання, зокрема в листах.
        // Список довірених проксі задає AppServiceProvider: config тут ще недоступний.
        $middleware->trustProxies(
            headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO,
        );
        // Лише хост із APP_URL та його піддомени (поза local і тестами).
        $middleware->trustHosts();
        $middleware->append(SecurityHeaders::class);
        $middleware->prependToGroup('web', ForceUrlFromIncomingRequest::class);
        $middleware->appendToGroup('web', SetShopLocale::class);
        $middleware->appendToGroup('web', SetNoReferrerPolicy::class);
        $middleware->appendToGroup('web', RecordShopAnalytics::class);
        $middleware->alias([
            'account.orders' => AllowAccountOrdersWithoutGoogle::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('account.index'));
        $middleware->validateCsrfTokens(except: [
            'payments/liqpay/callback',
            'payments/wayforpay/callback',
            // POST від платіжки на returnUrl без нашого CSRF-токена
            'checkout/success/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
