<?php

namespace App\Support;

use App\Models\ShopIntegrationSetting;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class ShopMailBranding
{
    public static function logoPath(): string
    {
        $preferred = public_path('images/zoogle-logo-new.png');
        if (is_file($preferred)) {
            return $preferred;
        }

        return public_path('images/zoogle-logo.png');
    }

    public static function logoSrc(mixed $message): string
    {
        if (is_object($message) && method_exists($message, 'embed') && is_file(self::logoPath())) {
            return $message->embed(self::logoPath());
        }

        return asset('images/zoogle-logo-new.png');
    }

    /**
     * Картинка з public storage всередині листа. Спочатку зменшена копія, інакше оригінал.
     * GIF і відео без кадру-постера не вкладаються.
     */
    public static function publicImageSrc(mixed $message, ?string $relativePath): ?string
    {
        $path = self::mailImageRelativePath($relativePath);
        if ($path === null) {
            return null;
        }

        $absolute = Storage::disk('public')->path($path);
        if (is_string($absolute) && is_file($absolute) && is_object($message) && method_exists($message, 'embed')) {
            try {
                $embedded = $message->embed(self::emailThumbPath($absolute));

                return is_string($embedded) && $embedded !== '' ? $embedded : null;
            } catch (Throwable) {
                // Нижче лишається звичайне посилання, якщо файл віддається з сайту.
            }
        }

        return PublicStorageUrl::forPath($path);
    }

    private static function mailImageRelativePath(?string $relativePath): ?string
    {
        $normalized = StoragePublicPath::normalize($relativePath);
        if ($normalized === null) {
            return null;
        }

        $disk = Storage::disk('public');
        $type = ListingMedia::typeForPath($normalized);
        $kind = $type === ListingMedia::TYPE_IMAGE ? 'card' : 'poster';
        $derivative = ListingPhotoDerivative::derivativeRelativePath($normalized, $kind);

        if ($disk->exists($derivative)) {
            return $derivative;
        }

        if ($type === ListingMedia::TYPE_IMAGE && $disk->exists($normalized)) {
            return $normalized;
        }

        return null;
    }

    /**
     * Мініатюра для листа: довша сторона 160px, щоб оригінал товару не роздував повідомлення.
     */
    private static function emailThumbPath(string $absolute): string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg')) {
            return $absolute;
        }

        $dir = storage_path('framework/cache/mail-thumbs');
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            return $absolute;
        }

        $dest = $dir.'/'.md5($absolute.'|'.(string) filemtime($absolute).'|'.(string) filesize($absolute)).'.jpg';
        if (is_file($dest)) {
            return $dest;
        }

        $bytes = file_get_contents($absolute);
        if ($bytes === false) {
            return $absolute;
        }

        $src = @imagecreatefromstring($bytes);
        if ($src === false) {
            return $absolute;
        }

        $width = imagesx($src);
        $height = imagesy($src);
        $scale = min(1, 160 / max($width, $height, 1));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $thumb = imagecreatetruecolor($targetWidth, $targetHeight);
        if ($thumb === false) {
            imagedestroy($src);

            return $absolute;
        }

        $background = imagecolorallocate($thumb, 241, 243, 244);
        imagefilledrectangle($thumb, 0, 0, $targetWidth, $targetHeight, $background);
        imagecopyresampled($thumb, $src, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        $written = imagejpeg($thumb, $dest, 82);
        imagedestroy($src);
        imagedestroy($thumb);

        return $written && is_file($dest) ? $dest : $absolute;
    }

    public static function money(float|int|string|null $amount): string
    {
        return number_format((float) $amount, 2, ',', ' ').' ₴';
    }

    public static function contactEmail(): ?string
    {
        try {
            $email = trim((string) (ShopIntegrationSetting::record()->contact_email ?? ''));
        } catch (Throwable) {
            return null;
        }

        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }
}
