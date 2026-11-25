<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Версія сайту (файл VERSION) і дата заливки для підпису внизу головної.
 *
 * Дата заливки — коли на сервері востаннє виконали `artisan optimize`: інструкція релізу вимагає його
 * після кожної заливки, а сам VERSION з архіву зберігає дату збірки, а не заливки.
 */
final class SiteRelease
{
    public static function version(): ?string
    {
        $file = base_path('VERSION');
        $version = is_file($file) ? trim((string) file_get_contents($file)) : '';

        return $version !== '' ? $version : null;
    }

    public static function deployedAt(): ?Carbon
    {
        foreach ([base_path('bootstrap/cache/config.php'), base_path('VERSION')] as $file) {
            if (is_file($file) && ($time = filemtime($file)) !== false) {
                return Carbon::createFromTimestamp($time, config('app.timezone'));
            }
        }

        return null;
    }
}
