<?php

namespace App\Support;

use App\Models\Bundle;
use App\Models\Product;
use Filament\Forms\Components\FileUpload;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use League\Flysystem\UnableToCheckFileExistence;

/**
 * Filament ->image() додає acceptedFileTypes image/* → правило mimetypes:image/*,
 * яке Laravel не приймає (потрібні image/jpeg, image/png, …).
 * ListingPhotoFileUpload використовує mimes:… — надійніше на хостингу з кривим fileinfo.
 */
final class ListingPhotoUpload
{
    /**
     * @return list<string>
     */
    public static function extensions(): array
    {
        return [
            'jpeg',
            'jpg',
            'png',
            'gif',
            'webp',
            'heic',
            'heif',
        ];
    }

    /**
     * @return list<string>
     */
    public static function mimeTypes(): array
    {
        return [
            'image/jpeg',
            'image/pjpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/heic',
            'image/heif',
        ];
    }

    public static function make(string $name): ListingPhotoFileUpload
    {
        return ListingPhotoFileUpload::make($name);
    }

    public static function applyAcceptedImageTypes(FileUpload $upload): FileUpload
    {
        return $upload->acceptedFileTypes(self::mimeTypes());
    }

    /**
     * Прев’ю збережених файлів через asset('storage/…'), не Storage::url() (APP_URL на хостингу).
     */
    public static function applyPublicPreviewUrl(FileUpload $upload): FileUpload
    {
        return $upload->getUploadedFileUsing(function (FileUpload $component, string $file, string|array|null $storedFileNames): ?array {
            /** @var FilesystemAdapter $storage */
            $storage = $component->getDisk();
            $shouldFetchFileInformation = $component->shouldFetchFileInformation();

            if ($shouldFetchFileInformation) {
                try {
                    if (! $storage->exists($file)) {
                        return null;
                    }
                } catch (UnableToCheckFileExistence) {
                    return null;
                }
            }

            $url = PublicStorageUrl::forPath($file);
            if ($url === null) {
                return null;
            }

            return [
                'name' => ($component->isMultiple() ? ($storedFileNames[$file] ?? null) : $storedFileNames) ?? basename($file),
                'size' => $shouldFetchFileInformation ? $storage->size($file) : 0,
                'type' => $shouldFetchFileInformation ? $storage->mimeType($file) : null,
                'url' => $url,
            ];
        });
    }

    /**
     * Каталог галереї товару: id з $record або з Filament getRecord() (не draft при редагуванні).
     */
    public static function productOptionValuePhotosDirectory(?Product $record, FileUpload $component): string
    {
        return self::productListingStorageDirectory($record, $component, 'option-value-photos');
    }

    public static function productListingPhotosDirectory(?Product $record, FileUpload $component): string
    {
        return self::productListingStorageDirectory($record, $component, 'photos');
    }

    public static function productListingStorageDirectory(?Product $record, FileUpload $component, string $suffix): string
    {
        $id = $record?->getKey()
            ?? self::recordKeyFromComponent($component)
            ?? 'draft';

        $id = is_numeric($id) && (int) $id > 0 ? (int) $id : 'draft';

        return 'products/'.$id.'/'.$suffix;
    }

    public static function bundleListingPhotosDirectory(?Bundle $record, FileUpload $component): string
    {
        return self::bundleListingStorageDirectory($record, $component, 'photos');
    }

    public static function bundleOptionValuePhotosDirectory(?Bundle $record, FileUpload $component): string
    {
        return self::bundleListingStorageDirectory($record, $component, 'option-value-photos');
    }

    public static function bundleListingStorageDirectory(?Bundle $record, FileUpload $component, string $suffix): string
    {
        $id = $record?->getKey()
            ?? self::recordKeyFromComponent($component)
            ?? 'draft';

        $id = is_numeric($id) && (int) $id > 0 ? (int) $id : 'draft';

        return 'bundles/'.$id.'/'.$suffix;
    }

    private static function recordKeyFromComponent(FileUpload $component): int|string|null
    {
        $record = $component->getRecord();

        if ($record instanceof Model) {
            return $record->getKey();
        }

        return null;
    }

    public static function applyImageEditorIfAvailable(FileUpload $upload): FileUpload
    {
        if (! extension_loaded('gd')) {
            return $upload;
        }

        return $upload
            ->imageEditor()
            ->imageEditorViewportWidth(480)
            ->imageEditorViewportHeight(480);
    }
}
