<?php

namespace App\Support;

use App\Models\Bundle;
use App\Models\Product;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Utilities\Get;
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
        return array_values(array_unique(array_merge(
            self::imageExtensions(),
            self::videoExtensions(),
        )));
    }

    /**
     * @return list<string>
     */
    public static function imageExtensions(): array
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
    public static function videoExtensions(): array
    {
        return ListingMedia::videoExtensions();
    }

    /**
     * @return list<string>
     */
    public static function mimeTypes(): array
    {
        return array_values(array_unique(array_merge(
            self::imageMimeTypes(),
            self::videoMimeTypes(),
        )));
    }

    /**
     * @return list<string>
     */
    public static function imageMimeTypes(): array
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

    /**
     * @return list<string>
     */
    public static function videoMimeTypes(): array
    {
        return [
            'video/mp4',
            'video/webm',
            'video/quicktime',
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
                'type' => $shouldFetchFileInformation ? self::mimeTypeForPreview($storage, $file) : null,
                'url' => $url,
            ];
        });
    }

    /**
     * FilePond у Filament не показує прев’ю, якщо fileinfo на хостингу віддає application/octet-stream для JPEG.
     */
    private static function mimeTypeForPreview(FilesystemAdapter $storage, string $path): ?string
    {
        $detected = $storage->mimeType($path);
        if (
            is_string($detected)
            && $detected !== ''
            && ! in_array($detected, ['application/octet-stream', 'binary/octet-stream'], true)
        ) {
            return $detected;
        }

        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'heic' => 'image/heic',
            'heif' => 'image/heif',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'mov' => 'video/quicktime',
            default => is_string($detected) && $detected !== '' ? $detected : null,
        };
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

    public static function applyBannerImageEditorIfAvailable(FileUpload $upload): FileUpload
    {
        if (! extension_loaded('gd')) {
            return $upload;
        }

        return $upload
            ->imageEditor()
            ->imageEditorViewportWidth(960)
            ->imageEditorViewportHeight(320);
    }

    public static function applyBannerPhotosLayout(FileUpload $upload): FileUpload
    {
        return self::applyPublicPreviewUrl(
            self::applyAcceptedImageTypes($upload)
        )
            ->itemPanelAspectRatio('1:1')
            ->imagePreviewHeight('76')
            ->fetchFileInformation(false)
            ->maxParallelUploads(1)
            ->placeholder(
                'Перетягніть зображення банера<br><span class="filepond--label-action">Обрати файл</span>'
            )
            ->extraAttributes(['class' => 'fi-listing-photos-layout fi-header-banner-upload'], merge: true);
    }

    public static function headerBannerDraftDirectory(): string
    {
        return 'header-banners/draft';
    }

    public static function headerBannerDirectory(int $bannerId): string
    {
        return 'header-banners/'.$bannerId;
    }

    public static function headerBannerRowDirectory(Get $get): string
    {
        $bannerId = max(0, (int) ($get('id') ?? 0));
        if ($bannerId > 0) {
            return self::headerBannerDirectory($bannerId);
        }

        $uploadKey = trim((string) ($get('upload_key') ?? ''));
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', $uploadKey) ?? '';
        if ($safe === '') {
            $safe = 'new';
        }

        return self::headerBannerDraftDirectory().'/'.$safe;
    }
}
