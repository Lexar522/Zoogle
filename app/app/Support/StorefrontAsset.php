<?php

namespace App\Support;

/**
 * Адреса статичного файлу з public/ з версією за часом зміни: браузер кешує файл надовго,
 * а після деплою з новим вмістом бере свіжий.
 *
 * Скрипти вітрини лежать у public/js/storefront як звичайні (не модульні) файли: вони писались
 * як inline-код і покладаються на нестрогий режим та порядок виконання, тож у Vite-модулі їх не переносимо.
 */
final class StorefrontAsset
{
    public static function url(string $path): string
    {
        $path = ltrim($path, '/');
        $file = public_path($path);
        $version = is_file($file) ? (string) filemtime($file) : '';

        return asset($path).($version !== '' ? '?v='.$version : '');
    }
}
