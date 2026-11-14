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
     * Один файл зі стану Filament FileUpload (рядок або масив з шляхом у ключі/значенні).
     */
    public static function normalizeFileUploadState(mixed $state): ?string
    {
        if (is_string($state)) {
            return self::normalize($state);
        }

        if (! is_array($state)) {
            return null;
        }

        foreach ($state as $key => $value) {
            if (is_string($key) && ! is_numeric($key)) {
                $normalized = self::normalize($key);
                if ($normalized !== null) {
                    return $normalized;
                }
            }

            if (is_string($value)) {
                $normalized = self::normalize($value);
                if ($normalized !== null) {
                    return $normalized;
                }
            }
        }

        return null;
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
        foreach ($photos as $key => $path) {
            if (is_string($key) && ! is_numeric($key)) {
                $normalized = self::normalize($key);
                if ($normalized !== null) {
                    $out[] = $normalized;

                    continue;
                }
            }

            $normalized = self::normalize($path);
            if ($normalized !== null) {
                $out[] = $normalized;
            }
        }

        return $out;
    }
}
