<?php

declare(strict_types=1);

/**
 * Laravel Pail потребує пакет laravel/pail (require-dev) і розширення pcntl (CLI).
 * Якщо чогось немає — читаємо storage/logs/laravel.log через tail -f, щоб
 * concurrently не зупиняв увесь dev через помилку logs-процесу.
 */
$root = dirname(__DIR__);
chdir($root);

$log = $root.'/storage/logs/laravel.log';
$pailInstalled = is_dir($root.'/vendor/laravel/pail');
$pcntlAvailable = function_exists('pcntl_fork');

if ($pailInstalled && $pcntlAvailable) {
    passthru('php artisan pail --timeout=0', $code);
    exit($code);
}

if (! $pailInstalled) {
    fwrite(STDERR, "[dev] laravel/pail не встановлено — запустіть composer install (dev-залежності).\n");
    fwrite(STDERR, "[dev] Читаю {$log} (tail -f).\n");
} else {
    fwrite(STDERR, "[dev] Розширення php pcntl недоступне — Pail не запущено. Читаю {$log} (tail -f).\n");
}

if (! is_dir(dirname($log))) {
    mkdir(dirname($log), 0775, true);
}
if (! is_file($log)) {
    touch($log);
}

if (PHP_OS_FAMILY === 'Windows') {
    fwrite(STDERR, "[dev] Увімкніть pcntl у WSL або дивіться лог у storage/logs вручну.\n");
    exit(1);
}

passthru('exec tail -n0 -f '.escapeshellarg($log), $code);
exit($code);
