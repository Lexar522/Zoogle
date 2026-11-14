<?php

namespace App\Filament\Admin\Concerns;

use App\Models\Product;
use App\Support\ListingMedia;
use App\Support\StoragePublicPath;
use Illuminate\Support\Facades\Schema;
use App\Support\CatalogProductsTable;

/**
 * Метадані кадрування галереї (focus_x / focus_y, тип медіа).
 */
trait HandlesListingPhotoMeta
{
    protected function sanitizePhotoMetaForForm(array $data): array
    {
        if (! $this->productsTableHasPhotoMeta()) {
            unset($data['photo_meta']);

            return $data;
        }

        $photos = StoragePublicPath::normalizeList($data['photos'] ?? []);
        $data['photo_meta'] = ListingMedia::syncMetaWithPaths(
            $photos,
            ListingMedia::normalizeMetaMap($data['photo_meta'] ?? [])
        );

        return $data;
    }

    protected function sanitizePhotoMetaForSave(array $data, ?Product $record): array
    {
        if (! $this->productsTableHasPhotoMeta()) {
            unset($data['photo_meta']);

            return $data;
        }

        $photos = StoragePublicPath::normalizeList($data['photos'] ?? []);
        $existingMeta = ListingMedia::normalizeMetaMap(
            is_array($data['photo_meta'] ?? null)
                ? $data['photo_meta']
                : ($record instanceof Product ? ($record->photo_meta ?? []) : [])
        );

        if ($record instanceof Product && (int) $record->getKey() > 0) {
            $previousPhotos = StoragePublicPath::normalizeList($record->photos);
            $existingMeta = ListingMedia::remapMetaAfterPathChanges(
                $existingMeta,
                $previousPhotos,
                $photos
            );
        }

        $data['photo_meta'] = ListingMedia::syncMetaWithPaths($photos, $existingMeta);

        return $data;
    }

    protected function productsTableHasPhotoMeta(): bool
    {
        return Schema::hasColumn(CatalogProductsTable::name(), 'photo_meta');
    }
}
