<?php

use App\Http\Middleware\ForceUrlFromIncomingRequest;
use App\Http\Middleware\SetShopLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Підписані URL (Livewire upload/preview) мають збігатися зі схемою/host за проксі (HTTPS).
        $middleware->trustProxies(at: '*');
        $middleware->prependToGroup('web', ForceUrlFromIncomingRequest::class);
        $middleware->appendToGroup('web', SetShopLocale::class);
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
