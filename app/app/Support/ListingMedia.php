<?php

namespace App\Support;

/**
 * Типи медіа в галереї товару та метадані кадрування (focus_x / focus_y).
 */
final class ListingMedia
{
    public const TYPE_IMAGE = 'image';

    public const TYPE_GIF = 'gif';

    public const TYPE_VIDEO = 'video';

    /**
     * @return list<string>
     */
    public static function videoExtensions(): array
    {
        return ['mp4', 'webm', 'mov'];
    }

    public static function typeForPath(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'gif' => self::TYPE_GIF,
            'mp4', 'webm', 'mov' => self::TYPE_VIDEO,
            default => self::TYPE_IMAGE,
        };
    }

    public static function isVideoPath(string $path): bool
    {
        return self::typeForPath($path) === self::TYPE_VIDEO;
    }

    public static function isGifPath(string $path): bool
    {
        return self::typeForPath($path) === self::TYPE_GIF;
    }

    public static function shouldSkipImageProcessing(string $path): bool
    {
        $type = self::typeForPath($path);

        return $type === self::TYPE_VIDEO || $type === self::TYPE_GIF;
    }

    /**
     * @return array{type: string, focus_x: float, focus_y: float, poster?: string}
     */
    public static function defaultMetaForPath(string $path): array
    {
        return [
            'type' => self::typeForPath($path),
            'focus_x' => 50.0,
            'focus_y' => 50.0,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $entry
     * @return array{type: string, focus_x: float, focus_y: float, poster?: string}
     */
    public static function normalizeMetaEntry(?array $entry, string $path): array
    {
        $defaults = self::defaultMetaForPath($path);

        if ($entry === null) {
            return $defaults;
        }

        $type = is_string($entry['type'] ?? null) && $entry['type'] !== ''
            ? (string) $entry['type']
            : $defaults['type'];

        if (! in_array($type, [self::TYPE_IMAGE, self::TYPE_GIF, self::TYPE_VIDEO], true)) {
            $type = $defaults['type'];
        }

        // Розширення файлу має пріоритет — GIF/відео не можуть стати «image» через помилковий meta.
        if (in_array($defaults['type'], [self::TYPE_GIF, self::TYPE_VIDEO], true)) {
            $type = $defaults['type'];
        }

        $focusX = self::clampPercent($entry['focus_x'] ?? $defaults['focus_x']);
        $focusY = self::clampPercent($entry['focus_y'] ?? $defaults['focus_y']);

        $normalized = [
            'type' => $type,
            'focus_x' => $focusX,
            'focus_y' => $focusY,
        ];

        $poster = StoragePublicPath::normalize($entry['poster'] ?? null);
        if ($poster !== null && $type === self::TYPE_VIDEO) {
            $normalized['poster'] = $poster;
        }

        return $normalized;
    }

    /**
     * @return array<string, array{type: string, focus_x: float, focus_y: float, poster?: string}>
     */
    public static function normalizeMetaMap(mixed $meta): array
    {
        if (! is_array($meta)) {
            return [];
        }

        $out = [];
        foreach ($meta as $path => $entry) {
            $normalizedPath = StoragePublicPath::normalize(is_string($path) ? $path : null);
            if ($normalizedPath === null) {
                continue;
            }

            $entryArray = is_array($entry) ? $entry : [];
            $out[$normalizedPath] = self::normalizeMetaEntry($entryArray, $normalizedPath);
        }

        return $out;
    }

    /**
     * @param  list<string>  $paths
     * @param  array<string, array<string, mixed>>  $existingMeta
     * @return array<string, array{type: string, focus_x: float, focus_y: float, poster?: string}>
     */
    public static function syncMetaWithPaths(array $paths, array $existingMeta): array
    {
        $existingMeta = self::normalizeMetaMap($existingMeta);
        $out = [];

        foreach ($paths as $path) {
            $normalizedPath = StoragePublicPath::normalize($path);
            if ($normalizedPath === null) {
                continue;
            }

            $out[$normalizedPath] = self::normalizeMetaEntry(
                $existingMeta[$normalizedPath] ?? null,
                $normalizedPath
            );
        }

        return $out;
    }

    /**
     * @param  array<string, array<string, mixed>>  $meta
     * @param  list<string>  $oldPaths
     * @param  list<string>  $newPaths
     * @return array<string, array{type: string, focus_x: float, focus_y: float, poster?: string}>
     */
    public static function remapMetaAfterPathChanges(array $meta, array $oldPaths, array $newPaths): array
    {
        $meta = self::normalizeMetaMap($meta);
        $oldPaths = StoragePublicPath::normalizeList($oldPaths);
        $newPaths = StoragePublicPath::normalizeList($newPaths);

        if ($oldPaths === $newPaths) {
            return self::syncMetaWithPaths($newPaths, $meta);
        }

        $byBasename = [];
        foreach ($meta as $path => $entry) {
            $byBasename[strtolower(basename($path))] = $entry;
        }

        $remapped = [];
        foreach ($oldPaths as $index => $oldPath) {
            $newPath = $newPaths[$index] ?? null;
            if (! is_string($newPath) || $newPath === '' || $oldPath === $newPath) {
                continue;
            }

            if (isset($meta[$oldPath])) {
                $remapped[$newPath] = $meta[$oldPath];
            } elseif (isset($byBasename[strtolower(basename($oldPath))])) {
                $remapped[$newPath] = $byBasename[strtolower(basename($oldPath))];
            }
        }

        $merged = array_merge($meta, $remapped);

        return self::syncMetaWithPaths($newPaths, $merged);
    }

    /**
     * @param  array{type?: string, focus_x?: mixed, focus_y?: mixed, poster?: string|null}  $meta
     */
    public static function objectPositionStyle(array $meta): string
    {
        $focusX = self::clampPercent($meta['focus_x'] ?? 50);
        $focusY = self::clampPercent($meta['focus_y'] ?? 50);

        return 'object-fit: cover; object-position: '.$focusX.'% '.$focusY.'%;';
    }

    /**
     * @param  array<string, array<string, mixed>>  $metaMap
     * @return array{
     *     path: string,
     *     url: string,
     *     type: string,
     *     focus_x: float,
     *     focus_y: float,
     *     poster?: string,
     *     poster_url?: string,
     *     object_position: string
     * }
     */
    public static function storefrontItemForPath(string $path, array $metaMap): array
    {
        $normalizedPath = StoragePublicPath::normalize($path) ?? $path;
        $entry = self::normalizeMetaEntry($metaMap[$normalizedPath] ?? null, $normalizedPath);
        $url = PublicStorageUrl::forPath($normalizedPath) ?? asset('storage/'.$normalizedPath);

        $item = [
            'path' => $normalizedPath,
            'url' => $url,
            'card_url' => ListingPhotoDerivative::cardUrlForPath($normalizedPath),
            'card_small_url' => ListingPhotoDerivative::thumbUrlForPath($normalizedPath),
            'type' => $entry['type'],
            'focus_x' => $entry['focus_x'],
            'focus_y' => $entry['focus_y'],
            'object_position' => self::objectPositionStyle($entry),
        ];

        if (! empty($entry['poster'])) {
            $item['poster'] = $entry['poster'];
            $item['poster_url'] = PublicStorageUrl::forPath($entry['poster']) ?? asset('storage/'.$entry['poster']);
        }

        return $item;
    }

    /**
     * @param  list<string>  $paths
     * @param  array<string, array<string, mixed>>  $metaMap
     * @return list<array{
     *     path: string,
     *     url: string,
     *     type: string,
     *     focus_x: float,
     *     focus_y: float,
     *     poster?: string,
     *     poster_url?: string,
     *     object_position: string
     * }>
     */
    public static function storefrontItemsForPaths(array $paths, array $metaMap): array
    {
        $metaMap = self::normalizeMetaMap($metaMap);
        $items = [];

        foreach ($paths as $path) {
            $normalizedPath = StoragePublicPath::normalize($path);
            if ($normalizedPath === null || ListingPhotoDerivative::isDerivativePath($normalizedPath)) {
                continue;
            }

            $items[] = self::storefrontItemForPath($normalizedPath, $metaMap);
        }

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public static function firstCatalogPreviewItem(array $items): ?array
    {
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            return $item;
        }

        return null;
    }

    private static function clampPercent(mixed $value): float
    {
        if (! is_numeric($value)) {
            return 50.0;
        }

        return max(0.0, min(100.0, round((float) $value, 2)));
    }
}
