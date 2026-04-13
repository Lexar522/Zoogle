<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

class GoogleAuthController extends Controller
{
    private function googleOAuthMissingMessage(): string
    {
        $missing = [];
        if (! filled(config('services.google.client_id'))) {
            $missing[] = 'GOOGLE_CLIENT_ID';
        }
        if (! filled(config('services.google.client_secret'))) {
            $missing[] = 'GOOGLE_CLIENT_SECRET';
        }

        $hint = $missing === []
            ? ''
            : ' У файлі .env додайте: '.implode(', ', $missing).'. Потім виконайте php artisan config:clear.';

        return 'Вхід через Google ще не налаштований.'.$hint.' У Google Cloud Console додайте Authorized redirect URI для кожного хосту, з якого заходите (наприклад http://127.0.0.1:8000/auth/google/callback та http://localhost:8000/auth/google/callback).';
    }

    private function isGoogleOAuthConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }

    /**
     * Той самий URI має бути в Google Console і в запиті authorize, і в обміні code → token.
     * Беремо схему/хост з поточного запиту, щоб localhost і 127.0.0.1 не роз’їжджались з APP_URL.
     */
    private function googleOAuthCallbackUrl(Request $request): string
    {
        $path = parse_url(route('auth.google.callback', [], true), PHP_URL_PATH) ?: '/auth/google/callback';

        return rtrim($request->getSchemeAndHttpHost().$request->getBaseUrl(), '/').$path;
    }

    public function redirect(Request $request): RedirectResponse|SymfonyRedirect
    {
        if (! $this->isGoogleOAuthConfigured()) {
            return redirect()->route('catalog.index')
                ->with('error', $this->googleOAuthMissingMessage());
        }

        return Socialite::driver('google')
            ->stateless()
            ->redirectUrl($this->googleOAuthCallbackUrl($request))
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! $this->isGoogleOAuthConfigured()) {
            return redirect()->route('catalog.index')
                ->with('error', $this->googleOAuthMissingMessage());
        }

        try {
            $googleUser = Socialite::driver('google')
                ->stateless()
                ->redirectUrl($this->googleOAuthCallbackUrl($request))
                ->user();
        } catch (InvalidStateException) {
            return redirect()->route('catalog.index')
                ->with(
                    'error',
                    'Сесія входу через Google не збереглась. Відкрийте сайт у тому ж браузері та з того ж адреси (localhost або 127.0.0.1), увімкніть куки та спробуйте знову.'
                );
        } catch (\Throwable $e) {
            report($e);

            $message = config('app.debug')
                ? 'Помилка Google OAuth: '.$e->getMessage()
                : 'Не вдалося увійти через Google. Спробуйте ще раз.';

            return redirect()->route('catalog.index')->with('error', $message);
        }

        $googleId = (string) $googleUser->getId();
        $email = $googleUser->getEmail();
        if ($email === null || $email === '') {
            return redirect()->route('catalog.index')
                ->with('error', 'Google не надав email для акаунта.');
        }

        $email = strtolower(trim($email));
        $name = trim((string) ($googleUser->getName() ?: ''));
        if ($name === '') {
            $name = strstr($email, '@', true) ?: $email;
        }
        $avatar = $googleUser->getAvatar();

        $user = User::query()->where('google_id', $googleId)->first();

        if ($user === null) {
            $existing = User::query()->where('email', $email)->first();
            if ($existing !== null) {
                if ($existing->google_id !== null && $existing->google_id !== $googleId) {
                    return redirect()->route('catalog.index')
                        ->with('error', 'Цей email уже прив’язаний до іншого Google-акаунта.');
                }
                $existing->forceFill([
                    'google_id' => $googleId,
                    'name' => $name,
                    'avatar' => $avatar,
                    'email_verified_at' => $existing->email_verified_at ?? now(),
                ])->save();
                $user = $existing;
            } else {
                $user = new User;
                $user->forceFill([
                    'name' => $name,
                    'email' => $email,
                    'google_id' => $googleId,
                    'avatar' => $avatar,
                    'email_verified_at' => now(),
                ]);
                $user->password = null;
                $user->save();
            }
        } else {
            $user->forceFill([
                'name' => $name,
                'avatar' => $avatar,
            ])->save();
        }

        Auth::login($user, true);

        return redirect()->intended(route('account.index'));
    }
}
