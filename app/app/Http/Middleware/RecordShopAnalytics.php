<?php

namespace App\Http\Middleware;

use App\Services\ShopAnalytics;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordShopAnalytics
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->isSuccessful()) {
            ShopAnalytics::prepareVisitor($request);
        }

        return $response;
    }

    /**
     * Запис у базу після відправки відповіді (на PHP-FPM — після fastcgi_finish_request),
     * щоб кілька INSERT/UPDATE статистики не додавались до часу завантаження сторінки.
     */
    public function terminate(Request $request, Response $response): void
    {
        if ($response->isSuccessful()) {
            ShopAnalytics::recordPageView($request);
        }
    }
}
