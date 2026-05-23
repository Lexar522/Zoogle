<?php

namespace App\Support;

/**
 * Відносний шлях у disk public (без префікса storage/) для asset('storage/…').
 */
final class StoragePublicPath
{
    public static function normalize(mixed $path): ?string
    {
        if (! is_string($path)) {
            return null;
        }

        $path = trim($path);
        if ($path === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $path)) {
            $parsedPath = parse_url($path, PHP_URL_PATH);
            $path = is_string($parsedPath) ? $parsedPath : $path;
        }

        $path = ltrim($path, '/');

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        return $path !== '' ? $path : null;
    }

    /**
     * @return list<string>
     */
    public static function normalizeList(mixed $photos): array
    {
        if (! is_array($photos)) {
            return [];
        }

        $out = [];
        foreach ($photos as $path) {
            $normalized = self::normalize($path);
            if ($normalized !== null) {
                $out[] = $normalized;
            }
        }

        return $out;
    }
}
