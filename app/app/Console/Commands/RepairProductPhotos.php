<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Support\StoragePublicPath;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class RepairProductPhotos extends Command
{
    protected $signature = 'shop:repair-product-photos {--dry-run : Лише показати, що буде оновлено}';

    protected $description = 'Відновити поле photos у БД з файлів у storage/app/public/products/{id}/photos';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $disk = Storage::disk('public');
        $repaired = 0;

        Product::query()
            ->orderBy('id')
            ->each(function (Product $product) use ($disk, $dryRun, &$repaired): void {
                $inDb = array_values(array_filter(
                    StoragePublicPath::normalizeList($product->photos),
                    fn (string $path): bool => $disk->exists($path)
                ));

                if ($inDb !== []) {
                    return;
                }

                $fromDisk = $product->photosPathsFromDisk();
                if ($fromDisk === []) {
                    return;
                }

                $this->line(sprintf(
                    'Товар #%d (%s): %d файл(ів) з диска',
                    $product->id,
                    $product->slug,
                    count($fromDisk)
                ));

                if (! $dryRun) {
                    $product->updateQuietly(['photos' => $fromDisk]);
                }

                $repaired++;
            });

        if ($repaired === 0) {
            $this->info('Нічого відновлювати — у всіх товарів photos збігається з диском або файлів немає.');

            return self::SUCCESS;
        }

        $this->info($dryRun
            ? "Знайдено {$repaired} товар(ів). Запустіть без --dry-run для запису в БД."
            : "Оновлено photos у {$repaired} товар(ів).");

        return self::SUCCESS;
    }
}
