<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Вирівнює корінь URL із фактичним хостом/портом запиту.
 *
 * Livewire (FileUpload) використовує підписані URL для upload/preview; якщо в адресному
 * рядку 127.0.0.1, а APP_URL=localhost (або навпаки), сесія та підпис не збігаються —
 * завантаження файлів у Filament «висить» на Loading.
 *
 * Filament/FilePond для вже збережених файлів будує прев’ю через Storage::disk('public')->url(),
 * де префікс береться з config filesystems (зазвичай APP_URL + /storage), а не з URL::forceRootUrl().
 * Якщо APP_URL не збігається з фактичним хостом (HTTPS за проксі, інший домен), браузер
 * запитує картинку з «чужого» URL — FilePond лишається в стані «Waiting for size» / Loading.
 */
class ForceUrlFromIncomingRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('app.force_url_from_incoming_request', true)) {
            $scheme = $request->getScheme();
            if (app()->environment('production') && str_starts_with((string) config('app.url'), 'https://')) {
                $scheme = 'https';
            }

            $root = $scheme.'://'.$request->getHttpHost().$request->getBasePath();
            URL::forceRootUrl($root);

            config([
                'filesystems.disks.public.url' => rtrim($root, '/').'/storage',
            ]);
        }

        return $next($request);
    }
}
