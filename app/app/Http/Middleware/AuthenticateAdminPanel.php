<?php

namespace App\Http\Middleware;

use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Database\Eloquent\Model;

class AuthenticateAdminPanel extends FilamentAuthenticate
{
    /**
     * Магазин і адмінка ділять один guard. Покупець із сесією сайту
     * не має бачити 403: виходимо з цього акаунта і показуємо вхід в адмінку.
     *
     * @param  array<string>  $guards
     */
    protected function authenticate($request, array $guards): void
    {
        $guard = Filament::auth();

        if (! $guard->check()) {
            $this->unauthenticated($request, $guards);

            return;
        }

        $this->auth->shouldUse(Filament::getAuthGuard());

        /** @var Model $user */
        $user = $guard->user();
        $panel = Filament::getCurrentOrDefaultPanel();

        $allowed = $user instanceof FilamentUser
            ? $user->canAccessPanel($panel)
            : config('app.env') === 'local';

        if ($allowed) {
            return;
        }

        $guard->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $this->unauthenticated($request, $guards);
    }
}
