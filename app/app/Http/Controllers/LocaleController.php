<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetShopLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function switch(Request $request, string $locale): RedirectResponse
    {
        if (! in_array($locale, SetShopLocale::LOCALES, true)) {
            $locale = 'uk';
        }
        $request->session()->put('locale', $locale);
        app()->setLocale($locale);

        return redirect()->to($this->safeReturnPath($request));
    }

    private function safeReturnPath(Request $request): string
    {
        $return = $request->query('return');
        if (! is_string($return) || $return === '') {
            $previous = url()->previous();

            return parse_url($previous, PHP_URL_HOST) === $request->getHost() ? $previous : route('home');
        }
        if (! str_starts_with($return, '/') || str_starts_with($return, '//') || str_contains($return, '\\')) {
            return route('home');
        }
        if (strlen($return) > 2048) {
            return route('home');
        }

        return $return;
    }
}
