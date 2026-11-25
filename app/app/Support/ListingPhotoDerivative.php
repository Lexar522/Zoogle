<?php

namespace App\Support;

use App\Models\ShopMediaSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Зменшені копії для вітрини. Оригінал лишається для головної галереї сторінки товару.
 */
final class ListingPhotoDerivative
{
    public const CARD_MAX_EDGE = 640;

    public const BANNER_MAX_WIDTH = 1600;

    public const WEBP_QUALITY = 75;

    public static function isDerivativePath(string $path): bool
    {
        // thumb — копії 320px з версії 1.1.85 (прибрані: картка з object-fit: cover розтягувала їх і мутніла).
        // Лишаються у списку, щоб shop:make-photo-derivatives не робив копій із цих файлів, поки вони є на сервері.
        return (bool) preg_match('/\.(?:card|thumb|poster|banner)\.webp$/i', str_replace('\\', '/', $path));
    }

    public static function kindForPath(string $path): string
    {
        $normalized = str_replace('\\', '/', ltrim($path, '/'));
        if (str_starts_with($normalized, 'header-banners/')) {
            return 'banner';
        }

        $type = ListingMedia::typeForPath($normalized);

        return $type === ListingMedia::TYPE_IMAGE ? 'card' : 'poster';
    }

    public static function derivativeRelativePath(string $path, ?string $kind = null): string
    {
        $path = str_replace('\\', '/', ltrim($path, '/'));
        $kind ??= self::kindForPath($path);
        $dir = dirname($path);
        $name = pathinfo($path, PATHINFO_FILENAME);
        $file = $name.'.'.$kind.'.webp';

        return ($dir !== '' && $dir !== '.') ? $dir.'/'.$file : $file;
    }

    /**
     * URL зменшеної копії. Для фото без готової копії — оригінал.
     * GIF і відео без кадру-постера не підставляють оригінал у картку.
     */
    public static function cardUrlForPath(?string $path): string
    {
        $normalized = StoragePublicPath::normalize($path);
        if ($normalized === null) {
            return '';
        }

        if (! ShopMediaSetting::optimizeImagesEnabled()) {
            return PublicStorageUrl::forPath($normalized) ?? '';
        }

        $kind = ListingMedia::typeForPath($normalized) === ListingMedia::TYPE_IMAGE ? 'card' : 'poster';
        $derivative = self::derivativeRelativePath($normalized, $kind);

        if (self::isUsable($derivative)) {
            return PublicStorageUrl::forPath($derivative) ?? '';
        }

        if ($kind === 'card') {
            return PublicStorageUrl::forPath($normalized) ?? '';
        }

        return '';
    }

    /** Порожній файл лишається після обірваного запису й дає білу картку. */
    private static function isUsable(string $derivative): bool
    {
        // Картка, мініатюра й og:image питають той самий файл кілька разів за сторінку.
        return StorefrontMemo::remember('photo-derivative.'.$derivative, function () use ($derivative): bool {
            $disk = Storage::disk('public');

            return $disk->exists($derivative) && $disk->size($derivative) > 0;
        });
    }

    public static function bannerUrlForPath(?string $path): string
    {
        $normalized = StoragePublicPath::normalize($path);
        if ($normalized === null) {
            return '';
        }

        if (! ShopMediaSetting::optimizeImagesEnabled()) {
            return PublicStorageUrl::forPath($normalized) ?? '';
        }

        $derivative = self::derivativeRelativePath($normalized, 'banner');
        if (self::isUsable($derivative)) {
            return PublicStorageUrl::forPath($derivative) ?? '';
        }

        return PublicStorageUrl::forPath($normalized) ?? '';
    }

    /**
     * Будує копію, якщо її ще немає або оригінал новіший.
     * Повертає відносний шлях копії або null.
     */
    public static function ensureForStoredPath(string $path, string $diskName = 'public'): ?string
    {
        if (! extension_loaded('gd') || ! function_exists('imagewebp')) {
            return null;
        }

        $path = str_replace('\\', '/', ltrim($path, '/'));
        if ($path === '' || self::isDerivativePath($path)) {
            return null;
        }

        $disk = Storage::disk($diskName);
        if (! $disk->exists($path)) {
            return null;
        }

        $sourceAbsolute = $disk->path($path);
        if (! is_string($sourceAbsolute) || ! is_file($sourceAbsolute)) {
            return null;
        }

        $kind = self::kindForPath($path);
        $relative = self::derivativeRelativePath($path, $kind);
        $destAbsolute = $disk->path($relative);
        if (! is_string($destAbsolute) || $destAbsolute === '') {
            return null;
        }

        if (is_file($destAbsolute) && filesize($destAbsolute) > 0 && filemtime($destAbsolute) >= filemtime($sourceAbsolute)) {
            return $relative;
        }

        $dir = dirname($destAbsolute);
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            return null;
        }

        try {
            $written = ListingMedia::isVideoPath($path)
                ? self::writePosterFromVideo($sourceAbsolute, $destAbsolute)
                : self::writeScaledWebp($sourceAbsolute, $destAbsolute, $kind === 'banner');
        } catch (Throwable $e) {
            Log::warning('ListingPhotoDerivative failed: '.$e->getMessage(), ['path' => $path]);

            return null;
        }

        if (! $written) {
            return null;
        }

        StorefrontMemo::forget('photo-derivative.'.$relative);

        return $relative;
    }

    private static function writePosterFromVideo(string $sourceAbsolute, string $destAbsolute): bool
    {
        $ffmpeg = trim((string) shell_exec('command -v ffmpeg 2>/dev/null'));
        if ($ffmpeg === '') {
            return false;
        }

        $frame = $destAbsolute.'.frame.png';
        $command = sprintf(
            '%s -y -ss 0 -i %s -frames:v 1 -vf %s %s',
            escapeshellarg($ffmpeg),
            escapeshellarg($sourceAbsolute),
            escapeshellarg('scale=\'min(640,iw)\':-2'),
            escapeshellarg($frame)
        );
        exec($command.' 2>/dev/null', $output, $code);
        if ($code !== 0 || ! is_file($frame)) {
            @unlink($frame);

            return false;
        }

        $ok = self::writeScaledWebp($frame, $destAbsolute, false);
        @unlink($frame);

        return $ok;
    }

    private static function writeScaledWebp(string $sourceAbsolute, string $destAbsolute, bool $banner): bool
    {
        $ext = strtolower(pathinfo($sourceAbsolute, PATHINFO_EXTENSION));
        $image = self::loadImage($sourceAbsolute, $ext);
        if ($image === null) {
            return false;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        if ($width < 1 || $height < 1) {
            imagedestroy($image);

            return false;
        }

        [$targetW, $targetH] = self::targetSize($width, $height, $banner);
        $canvas = imagecreatetruecolor($targetW, $targetH);
        if ($canvas === false) {
            imagedestroy($image);

            return false;
        }

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $targetW, $targetH, $transparent);
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetW, $targetH, $width, $height);
        imagedestroy($image);

        // Через тимчасовий файл: обірваний запис не залишає порожню копію на вітрині.
        $temporary = $destAbsolute.'.tmp';
        $ok = imagewebp($canvas, $temporary, self::WEBP_QUALITY);
        imagedestroy($canvas);

        if (! $ok || ! is_file($temporary) || filesize($temporary) === 0 || ! rename($temporary, $destAbsolute)) {
            @unlink($temporary);

            return false;
        }

        return true;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private static function targetSize(int $width, int $height, bool $banner): array
    {
        if ($banner) {
            if ($width <= self::BANNER_MAX_WIDTH) {
                return [$width, $height];
            }

            $scale = self::BANNER_MAX_WIDTH / $width;

            return [self::BANNER_MAX_WIDTH, max(1, (int) round($height * $scale))];
        }

        $long = max($width, $height);
        if ($long <= self::CARD_MAX_EDGE) {
            return [$width, $height];
        }

        $scale = self::CARD_MAX_EDGE / $long;

        return [
            max(1, (int) round($width * $scale)),
            max(1, (int) round($height * $scale)),
        ];
    }

    /**
     * @return \GdImage|null
     */
    private static function loadImage(string $path, string $ext): mixed
    {
        $info = @getimagesize($path);
        $type = is_array($info) ? (int) ($info[2] ?? 0) : 0;
        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_GIF => @imagecreatefromgif($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => match ($ext) {
                'jpg', 'jpeg' => @imagecreatefromjpeg($path),
                'png' => @imagecreatefrompng($path),
                'gif' => @imagecreatefromgif($path),
                'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
                default => false,
            },
        };

        if ($image === false) {
            return null;
        }

        if (function_exists('imagepalettetotruecolor')) {
            imagepalettetotruecolor($image);
        }

        return $image;
    }
}
