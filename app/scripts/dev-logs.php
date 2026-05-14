<?php

declare(strict_types=1);

/**
 * Laravel Pail потребує розширення pcntl (CLI). Без нього `php artisan pail`
 * завершується з кодом 1 — і concurrently з --kill-others зупиняв увесь dev.
 */
$root = dirname(__DIR__);
chdir($root);

if (function_exists('pcntl_fork')) {
    passthru('php artisan pail --timeout=0', $code);
    exit($code);
}

$log = $root.'/storage/logs/laravel.log';
fwrite(STDERR, '[dev] Розширення php pcntl недоступне — Pail не запущено. Читаю '.$log." (tail -f).\n");

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
