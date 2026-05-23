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
        $path = StoragePublicPath::normalize($relativePath);
        if ($path === null) {
            return null;
        }

        return asset('storage/'.$path);
    }
}
