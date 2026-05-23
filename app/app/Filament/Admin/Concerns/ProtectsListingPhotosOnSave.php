<?php

namespace App\Filament\Admin\Concerns;

use App\Models\Product;
use App\Support\StoragePublicPath;
use Illuminate\Support\Facades\Storage;

/**
 * Filament FileUpload інколи віддає порожній photos або лише livewire-tmp / биті URL —
 * після збереження галерея зникає з БД, хоча файли лишаються на диску.
 */
trait ProtectsListingPhotosOnSave
{
    protected function sanitizeListingPhotosForForm(array $data): array
    {
        if (! array_key_exists('photos', $data)) {
            return $data;
        }

        $data['photos'] = $this->persistableListingPhotoPaths(
            StoragePublicPath::normalizeList($data['photos'])
        );

        return $data;
    }

    /**
     * @return list<string>
     */
    protected function sanitizeListingPhotosForSave(mixed $incoming, ?Product $record): array
    {
        $normalized = StoragePublicPath::normalizeList($incoming);
        $persistable = $this->persistableListingPhotoPaths($normalized);

        if ($persistable !== []) {
            return $this->finalizeListingPhotosForProduct($persistable, $record);
        }

        // У формі були шляхи, але жоден не валідний (tmp, старий хост) — не затирати БД.
        if ($normalized !== [] && $record instanceof Product) {
            return $this->finalizeListingPhotosForProduct(
                $this->persistableListingPhotoPaths(
                    StoragePublicPath::normalizeList($record->photos)
                ),
                $record
            );
        }

        // Порожній стан: якщо FileUpload «втратив» існуючі файли, залишити те, що є на диску.
        if ($normalized === [] && $record instanceof Product) {
            $existing = $this->persistableListingPhotoPaths(
                StoragePublicPath::normalizeList($record->photos)
            );

            if ($existing !== []) {
                return $this->finalizeListingPhotosForProduct($existing, $record);
            }

            $fromDisk = $this->persistableListingPhotoPaths($record->photosPathsFromDisk());
            if ($fromDisk !== []) {
                return $this->finalizeListingPhotosForProduct($fromDisk, $record);
            }
        }

        return [];
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    protected function finalizeListingPhotosForProduct(array $paths, ?Product $record): array
    {
        if ($record instanceof Product && (int) $record->getKey() > 0) {
            $paths = $this->relocateDraftListingPhotos($paths, $record);
        }

        return $this->persistableListingPhotoPaths($paths);
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    protected function relocateDraftListingPhotos(array $paths, Product $record): array
    {
        $productId = (int) $record->getKey();
        if ($productId <= 0) {
            return $paths;
        }

        $disk = Storage::disk('public');
        $targetDirectory = 'products/'.$productId.'/photos';

        $out = [];
        foreach ($paths as $path) {
            if (! str_starts_with($path, 'products/draft/photos/')) {
                $out[] = $path;

                continue;
            }

            $filename = basename($path);
            $newPath = $targetDirectory.'/'.$filename;

            if ($disk->exists($newPath)) {
                if ($disk->exists($path)) {
                    $disk->delete($path);
                }
                $out[] = $newPath;

                continue;
            }

            if (! $disk->exists($path)) {
                continue;
            }

            $disk->makeDirectory($targetDirectory);
            $disk->move($path, $newPath);
            $out[] = $newPath;
        }

        return array_values(array_unique($out));
    }

    protected function mergeListingPhotosFromDisk(array $data, ?Product $record): array
    {
        if (! $record instanceof Product || (int) $record->id <= 0) {
            return $data;
        }

        $current = $this->persistableListingPhotoPaths(
            StoragePublicPath::normalizeList($data['photos'] ?? [])
        );

        if ($current !== []) {
            $data['photos'] = $current;

            return $data;
        }

        $fromDisk = $this->persistableListingPhotoPaths($record->photosPathsFromDisk());
        if ($fromDisk !== []) {
            $data['photos'] = $fromDisk;
        }

        return $data;
    }

    protected function repairListingPhotosInDatabaseIfNeeded(?Product $record): void
    {
        if (! $record instanceof Product || (int) $record->id <= 0) {
            return;
        }

        $inDb = $this->persistableListingPhotoPaths(
            StoragePublicPath::normalizeList($record->photos)
        );
        if ($inDb !== []) {
            return;
        }

        $fromDisk = $this->persistableListingPhotoPaths($record->photosPathsFromDisk());
        if ($fromDisk === []) {
            return;
        }

        $record->updateQuietly(['photos' => $fromDisk]);
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    protected function persistableListingPhotoPaths(array $paths): array
    {
        $disk = Storage::disk('public');

        return array_values(array_filter(
            $paths,
            fn (string $path): bool => ! str_starts_with($path, 'livewire-tmp/')
                && $disk->exists($path)
        ));
    }
}
