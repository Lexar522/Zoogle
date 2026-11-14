<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * BestHosting (і подібні shared-хости) часто блокують JPEG/PNG з /storage/, якщо браузер
 * надсилає Referer (403), тоді як curl без Referer і WebP — 200. Політика no-referrer
 * прибирає Referer з підзапитів img/favicon на тому ж домені.
 */
class SetNoReferrerPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! $response->headers->has('Referrer-Policy')) {
            $response->headers->set('Referrer-Policy', 'no-referrer');
        }

        return $response;
    }
}
