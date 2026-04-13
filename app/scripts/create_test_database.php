<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

$base = dirname(__DIR__);

if (is_file($base.'/.env')) {
    Dotenv\Dotenv::createImmutable($base)->safeLoad();
}

$host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$port = $_ENV['DB_PORT'] ?? '3306';
$user = $_ENV['DB_USERNAME'] ?? 'root';
$pass = $_ENV['DB_PASSWORD'] ?? '';
$db = 'sitezoo_test';

$dsn = sprintf('mysql:host=%s;port=%s', $host, $port);
$pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec(
    'CREATE DATABASE IF NOT EXISTS `'.str_replace('`', '``', $db).'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
);
echo "Database ready: {$db}\n";
