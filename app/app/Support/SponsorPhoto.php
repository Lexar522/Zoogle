<?php

namespace App\Support;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Фото опікуна: перезбереження в JPEG без метаданих (GPS тощо) і зменшення до розумного розміру.
 */
final class SponsorPhoto
{
    public const DIRECTORY = 'sponsorships';

    private const MAX_SIDE = 1200;

    private const JPEG_QUALITY = 85;

    public static function store(UploadedFile $file): ?string
    {
        $image = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (! $image instanceof GdImage) {
            return null;
        }

        $image = self::scaled(self::oriented($image, $file));

        ob_start();
        imagejpeg($image, null, self::JPEG_QUALITY);
        $jpeg = (string) ob_get_clean();
        imagedestroy($image);

        $path = self::DIRECTORY.'/'.Str::random(40).'.jpg';

        return Storage::disk('public')->put($path, $jpeg) ? $path : null;
    }

    public static function delete(?string $path): void
    {
        if (filled($path) && str_starts_with((string) $path, self::DIRECTORY.'/')) {
            Storage::disk('public')->delete((string) $path);
        }
    }

    /**
     * Фото з телефона часто «лежить на боці» — повертаємо за EXIF до того, як метадані зникнуть.
     */
    private static function oriented(GdImage $image, UploadedFile $file): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($file->getRealPath());
        $angle = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };
        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);
        if (! $rotated instanceof GdImage) {
            return $image;
        }
        imagedestroy($image);

        return $rotated;
    }

    private static function scaled(GdImage $image): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $longest = max($width, $height);
        if ($longest <= self::MAX_SIDE) {
            return $image;
        }

        $ratio = self::MAX_SIDE / $longest;
        $scaled = imagescale($image, max(1, (int) round($width * $ratio)), max(1, (int) round($height * $ratio)));
        if (! $scaled instanceof GdImage) {
            return $image;
        }
        imagedestroy($image);

        return $scaled;
    }
}
