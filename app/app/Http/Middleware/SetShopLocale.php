<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetShopLocale
{
    /** @var list<string> */
    public const LOCALES = ['uk', 'ru', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale', config('app.locale'));
        if (! is_string($locale) || ! in_array($locale, self::LOCALES, true)) {
            $locale = 'uk';
        }
        app()->setLocale($locale);

        return $next($request);
    }
}
