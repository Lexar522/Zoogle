<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Накладає логотип ZOOGLE на фото товарів/комплектів один раз при збереженні файлу.
 * На вітрині додаткова обробка не потрібна — віддається вже готовий файл.
 */
final class ListingPhotoWatermark
{
    public const ASSET_RELATIVE = 'images/zoogle-watermark.png';

    /** Частка ширини фото, яку займає знак (краще менше в куті). */
    private const WIDTH_RATIO = 0.28;

    /** Непрозорість знака 0–1. */
    private const OPACITY = 0.55;

    /** Відступ від країв у частках коротшої сторони фото. */
    private const PADDING_RATIO = 0.045;

    /** Мінімальний відступ у пікселях (щоб на малих фото теж був зазор). */
    private const PADDING_MIN_PX = 12;

    private const POSITION = 'top-left';

    /**
     * Чи варто накладати знак на цей шлях у public disk.
     */
    public static function shouldApplyToPath(string $path): bool
    {
        if (ListingMedia::shouldSkipImageProcessing($path) || ListingPhotoDerivative::isDerivativePath($path)) {
            return false;
        }

        $path = str_replace('\\', '/', ltrim($path, '/'));

        return (bool) preg_match(
            '#^(products|bundles)/(?:draft|\d+)/(?:photos|option-value-photos)/#',
            $path
        ) || (bool) preg_match(
            '#^product-variants/\d+/photos/#',
            $path
        );
    }

    public static function isEnabled(): bool
    {
        if (! extension_loaded('gd')) {
            return false;
        }

        $flag = config('shop.photo_watermark_enabled', env('SHOP_PHOTO_WATERMARK_ENABLED', true));

        return filter_var($flag, FILTER_VALIDATE_BOOL);
    }

    public static function watermarkAbsolutePath(): ?string
    {
        $path = public_path(self::ASSET_RELATIVE);

        return is_file($path) ? $path : null;
    }

    /**
     * Накладає водяний знак на файл на вказаному диску (зазвичай public).
     * Повертає true, якщо файл змінено.
     */
    public static function applyToStoredPath(string $path, string $diskName = 'public', bool $force = false): bool
    {
        if (! self::isEnabled() || ! self::shouldApplyToPath($path)) {
            return false;
        }

        $disk = Storage::disk($diskName);
        if (! $disk->exists($path)) {
            return false;
        }

        $absolute = $disk->path($path);
        if (! is_string($absolute) || $absolute === '' || ! is_file($absolute)) {
            return false;
        }

        return self::applyToAbsoluteFile($absolute, $force);
    }

    public static function markerPath(string $absolutePath): string
    {
        return $absolutePath.'.wm';
    }

    public static function originalBackupPath(string $absolutePath): string
    {
        return $absolutePath.'.wm-orig';
    }

    /**
     * @return bool true якщо файл перезаписано з водяним знаком
     */
    public static function applyToAbsoluteFile(string $absolutePath, bool $force = false): bool
    {
        if (! self::isEnabled()) {
            return false;
        }

        $marker = self::markerPath($absolutePath);
        $backup = self::originalBackupPath($absolutePath);

        if (! $force && is_file($marker)) {
            return false;
        }

        // --force: спочатку відновити оригінал, інакше знак накладеться поверх старого
        if ($force && is_file($backup)) {
            if (! @copy($backup, $absolutePath)) {
                Log::warning('ListingPhotoWatermark: failed to restore original backup', [
                    'path' => $absolutePath,
                ]);

                return false;
            }
            @unlink($marker);
        }

        $logoPath = self::watermarkAbsolutePath();
        if ($logoPath === null) {
            Log::warning('ListingPhotoWatermark: missing asset '.self::ASSET_RELATIVE);

            return false;
        }

        $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            return false;
        }

        try {
            // Оригінал лише до першого накладання (не зберігати вже водяне фото як .wm-orig)
            if (! is_file($backup) && ! is_file($marker)) {
                @copy($absolutePath, $backup);
            }

            $photo = self::loadImage($absolutePath, $ext);
            if ($photo === null) {
                return false;
            }

            $logo = self::loadImage($logoPath, 'png');
            if ($logo === null) {
                imagedestroy($photo);

                return false;
            }

            $photoW = imagesx($photo);
            $photoH = imagesy($photo);
            if ($photoW < 80 || $photoH < 80) {
                imagedestroy($photo);
                imagedestroy($logo);

                return false;
            }

            $targetW = max(48, (int) round($photoW * self::widthRatio()));
            $scale = $targetW / max(1, imagesx($logo));
            $targetH = max(24, (int) round(imagesy($logo) * $scale));

            $scaled = imagecreatetruecolor($targetW, $targetH);
            imagealphablending($scaled, false);
            imagesavealpha($scaled, true);
            $clear = imagecolorallocatealpha($scaled, 0, 0, 0, 127);
            imagefilledrectangle($scaled, 0, 0, $targetW, $targetH, $clear);
            imagealphablending($scaled, true);
            imagecopyresampled(
                $scaled,
                $logo,
                0,
                0,
                0,
                0,
                $targetW,
                $targetH,
                imagesx($logo),
                imagesy($logo)
            );
            imagedestroy($logo);

            $opacity = self::opacity();
            self::applyOpacityToImage($scaled, $opacity);

            $pad = self::paddingPx($photoW, $photoH);
            [$dstX, $dstY] = self::position($photoW, $photoH, $targetW, $targetH, $pad);

            imagealphablending($photo, true);
            imagecopy($photo, $scaled, $dstX, $dstY, 0, 0, $targetW, $targetH);
            imagedestroy($scaled);

            $ok = self::saveImage($photo, $absolutePath, $ext);
            imagedestroy($photo);

            if ($ok) {
                @file_put_contents($marker, (string) time()."\nposition=".self::resolvedPosition()."\n");
            }

            return $ok;
        } catch (Throwable $e) {
            Log::warning('ListingPhotoWatermark failed: '.$e->getMessage(), [
                'path' => $absolutePath,
            ]);

            return false;
        }
    }

    private static function resolvedPosition(): string
    {
        $pos = strtolower(trim((string) config(
            'shop.photo_watermark_position',
            env('SHOP_PHOTO_WATERMARK_POSITION', self::POSITION)
        )));

        return $pos !== '' ? $pos : self::POSITION;
    }

    private static function widthRatio(): float
    {
        $v = (float) config('shop.photo_watermark_width_ratio', env('SHOP_PHOTO_WATERMARK_WIDTH_RATIO', self::WIDTH_RATIO));

        return max(0.15, min(0.6, $v));
    }

    private static function opacity(): float
    {
        $v = (float) config('shop.photo_watermark_opacity', env('SHOP_PHOTO_WATERMARK_OPACITY', self::OPACITY));

        return max(0.1, min(1.0, $v));
    }

    private static function paddingPx(int $photoW, int $photoH): int
    {
        $ratio = (float) config(
            'shop.photo_watermark_padding_ratio',
            env('SHOP_PHOTO_WATERMARK_PADDING_RATIO', self::PADDING_RATIO)
        );
        $ratio = max(0.01, min(0.15, $ratio));
        $min = (int) config(
            'shop.photo_watermark_padding_min_px',
            env('SHOP_PHOTO_WATERMARK_PADDING_MIN_PX', self::PADDING_MIN_PX)
        );

        return max($min, (int) round(min($photoW, $photoH) * $ratio));
    }

    /**
     * @return array{0: int, 1: int}
     */
    private static function position(int $photoW, int $photoH, int $wmW, int $wmH, int $pad): array
    {
        $pos = self::resolvedPosition();

        $xRight = max(0, $photoW - $wmW - $pad);
        $yBottom = max(0, $photoH - $wmH - $pad);
        $xCenter = (int) max(0, round(($photoW - $wmW) / 2));
        $yCenter = (int) max(0, round(($photoH - $wmH) / 2));

        return match ($pos) {
            'bottom-right' => [$xRight, $yBottom],
            'bottom-left' => [$pad, $yBottom],
            'bottom-center' => [$xCenter, $yBottom],
            'top-right' => [$xRight, $pad],
            'top-left' => [$pad, $pad],
            'center' => [$xCenter, $yCenter],
            'top-center' => [$xCenter, $pad],
            default => [$pad, $pad], // fallback: top-left
        };
    }

    /**
     * @return \GdImage|null
     */
    private static function loadImage(string $path, string $ext): mixed
    {
        $image = match ($ext) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($path),
            'png' => @imagecreatefrompng($path),
            'gif' => @imagecreatefromgif($path),
            'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };

        if ($image === false) {
            return null;
        }

        if ($ext === 'png') {
            imagealphablending($image, true);
            imagesavealpha($image, true);
        }

        return $image;
    }

    /**
     * @param  \GdImage  $image
     */
    private static function saveImage(mixed $image, string $path, string $ext): bool
    {
        return match ($ext) {
            'jpg', 'jpeg' => imagejpeg($image, $path, 90),
            'png' => imagepng($image, $path, 6),
            'gif' => imagegif($image, $path),
            'webp' => function_exists('imagewebp') ? imagewebp($image, $path, 90) : false,
            default => false,
        };
    }

    /**
     * Множить alpha-канал на opacity (0–1), зберігаючи прозорість фону логотипу.
     *
     * @param  \GdImage  $image
     */
    private static function applyOpacityToImage(mixed $image, float $opacity): void
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $factor = max(0.0, min(1.0, $opacity));

        imagealphablending($image, false);
        imagesavealpha($image, true);

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $rgba = imagecolorat($image, $x, $y);
                $a = ($rgba & 0x7F000000) >> 24;
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;

                // GD alpha: 0 opaque … 127 transparent
                $visible = (127 - $a) / 127;
                $newVisible = $visible * $factor;
                $newA = (int) round(127 - ($newVisible * 127));
                $col = imagecolorallocatealpha($image, $r, $g, $b, $newA);
                imagesetpixel($image, $x, $y, $col);
            }
        }
    }
}
