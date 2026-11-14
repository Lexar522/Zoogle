<?php

namespace App\Console\Commands;

use App\Support\ListingMedia;
use App\Support\ListingPhotoDerivative;
use App\Support\StorefrontViewCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class MakePhotoDerivatives extends Command
{
    protected $signature = 'shop:make-photo-derivatives
                            {--dry-run : Лише показати файли, для яких буде копія}';

    protected $description = 'Збудувати зменшені WebP для карток, мініатюр і банерів';

    public function handle(): int
    {
        if (! extension_loaded('gd') || ! function_exists('imagewebp')) {
            $this->error('Потрібен GD з imagewebp.');

            return self::FAILURE;
        }

        $disk = Storage::disk('public');
        $root = $disk->path('');
        if (! is_dir($root)) {
            $this->error('Каталог public disk не існує: '.$root);

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $made = 0;
        $skipped = 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $absolute = $file->getPathname();
            $relative = ltrim(str_replace('\\', '/', substr($absolute, strlen(rtrim($root, DIRECTORY_SEPARATOR)))), '/');
            if (! self::shouldProcess($relative)) {
                continue;
            }

            if ($dryRun) {
                $this->line($relative);

                continue;
            }

            $result = ListingPhotoDerivative::ensureForStoredPath($relative);
            if ($result === null) {
                $skipped++;
                $this->warn('Пропущено: '.$relative);

                continue;
            }

            $made++;
        }

        if ($dryRun) {
            return self::SUCCESS;
        }

        // Банери в шапці кешовані з адресою оригіналу, поки копії не було.
        StorefrontViewCache::bump();
        $this->info("Готово. Копій: {$made}, пропущено: {$skipped}.");

        return self::SUCCESS;
    }

    private static function shouldProcess(string $relative): bool
    {
        if (ListingPhotoDerivative::isDerivativePath($relative)) {
            return false;
        }

        if (str_ends_with($relative, '.wm') || str_ends_with($relative, '.wm-orig')) {
            return false;
        }

        $ext = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
        $image = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
        $video = in_array($ext, ListingMedia::videoExtensions(), true);
        if (! $image && ! $video) {
            return false;
        }

        return (bool) preg_match(
            '#^(products|bundles)/(?:draft|\d+)/(?:photos|option-value-photos)/#',
            $relative
        ) || (bool) preg_match('#^product-variants/\d+/photos/#', $relative)
            || (bool) preg_match('#^header-banners/#', $relative);
    }
}
