<?php

namespace App\Console\Commands;

use App\Support\ListingPhotoWatermark;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class WatermarkProductPhotos extends Command
{
    protected $signature = 'shop:watermark-product-photos
                            {--force : Відновити оригінал (.wm-orig) якщо є і накласти знак знову}
                            {--dry-run : Лише показати, які файли будуть оброблені}';

    protected $description = 'Накласти водяний знак ZOOGLE на наявні фото товарів і комплектів';

    public function handle(): int
    {
        if (! ListingPhotoWatermark::isEnabled()) {
            $this->error('Водяний знак вимкнено або GD недоступний.');

            return self::FAILURE;
        }

        if (ListingPhotoWatermark::watermarkAbsolutePath() === null) {
            $this->error('Не знайдено '.ListingPhotoWatermark::ASSET_RELATIVE.' у public/.');

            return self::FAILURE;
        }

        $this->line('Позиція: '.config('shop.photo_watermark_position'));
        if ($this->option('force')) {
            $this->warn('Force: без файлу .wm-orig знак накладеться ПОВЕРХ уже водяного фото.');
        }

        $disk = Storage::disk('public');
        $root = $disk->path('');
        if (! is_dir($root)) {
            $this->error('Каталог public disk не існує: '.$root);

            return self::FAILURE;
        }

        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');
        $processed = 0;
        $skipped = 0;
        $failed = 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $absolute = $file->getPathname();
            if (str_ends_with($absolute, '.wm')) {
                continue;
            }

            $relative = ltrim(str_replace('\\', '/', substr($absolute, strlen(rtrim($root, DIRECTORY_SEPARATOR)))), '/');
            if (! ListingPhotoWatermark::shouldApplyToPath($relative)) {
                continue;
            }

            $ext = strtolower($file->getExtension());
            if (! in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                continue;
            }

            if (! $force && is_file(ListingPhotoWatermark::markerPath($absolute))) {
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $this->line('[dry-run] '.$relative);
                $processed++;

                continue;
            }

            if (ListingPhotoWatermark::applyToStoredPath($relative, 'public', $force)) {
                $this->info('OK  '.$relative);
                $processed++;
            } else {
                $this->warn('SKIP/FAIL  '.$relative);
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Готово: оброблено {$processed}, пропущено {$skipped}, помилок {$failed}.");

        return self::SUCCESS;
    }
}
