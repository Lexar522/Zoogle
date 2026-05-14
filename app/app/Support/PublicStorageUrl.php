<?php

namespace App\Support;

/**
 * Повний URL до файлу в public storage (symlink storage/app/public → public/storage).
 * Для Filament ImageColumn/прев’ю надійніше за Storage::disk('public')->url() при зміщеному APP_URL.
 */
final class PublicStorageUrl
{
    public static function forPath(?string $relativePath): ?string
    {
        if ($relativePath === null || $relativePath === '') {
            return null;
        }

        $path = ltrim($relativePath, '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        return asset('storage/'.$path);
    }
}
