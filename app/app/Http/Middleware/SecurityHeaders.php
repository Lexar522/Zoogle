<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Базові захисні заголовки. CSP обмежує, звідки можна підключати скрипти: лише свій сайт і
 * відомі сервіси (карта, Google Maps, Google Analytics, піксель Facebook). Вбудовані скрипти
 * поки дозволені — вітрина й Filament ними користуються; але чужий <script src> уже не завантажиться.
 */
class SecurityHeaders
{
    /** @var list<string> */
    private const SCRIPT_HOSTS = [
        'https://unpkg.com',
        'https://maps.googleapis.com',
        'https://maps.gstatic.com',
        'https://www.googletagmanager.com',
        'https://www.google-analytics.com',
        'https://connect.facebook.net',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self)');
        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy());

        if (app()->environment('production') && str_starts_with((string) config('app.url'), 'https://')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=15552000');
        }

        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        return $response;
    }

    private function contentSecurityPolicy(): string
    {
        $policy = "frame-ancestors 'self'; base-uri 'self'; object-src 'none'";

        // `npm run dev`: скрипти віддає окремий Vite-сервер на іншому порту — не обмежуємо.
        if (Vite::isRunningHot()) {
            return $policy;
        }

        return $policy."; script-src 'self' 'unsafe-inline' 'unsafe-eval' ".implode(' ', self::SCRIPT_HOSTS);
    }
}
