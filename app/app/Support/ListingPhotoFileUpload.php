<?php

namespace App\Support;

use Closure;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\FileUpload;
use Illuminate\Contracts\Support\Arrayable;
use League\Flysystem\UnableToCheckFileExistence;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * FileUpload для фото товарів: acceptedFileTypes для FilePond, але валідація через mimes,
 * не mimetypes (на shared-хостингу fileinfo часто віддає application/octet-stream для JPEG).
 * Після збереження на диск накладає водяний знак ZOOGLE (для шляхів products/bundles/…).
 */
class ListingPhotoFileUpload extends FileUpload
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->saveUploadedFileUsing(static function (BaseFileUpload $component, TemporaryUploadedFile $file): ?string {
            try {
                if (! $file->exists()) {
                    return null;
                }
            } catch (UnableToCheckFileExistence $exception) {
                return null;
            }

            if (
                $component->shouldMoveFiles() &&
                ($component->getDiskName() === (fn (): string => $this->disk)->call($file))
            ) {
                $newPath = trim($component->getDirectory().'/'.$component->getUploadedFileNameForStorage($file), '/');

                $component->getDisk()->move((fn (): string => $this->path)->call($file), $newPath);

                ListingPhotoWatermark::applyToStoredPath($newPath, $component->getDiskName());
                ListingPhotoDerivative::ensureForStoredPath($newPath, $component->getDiskName());

                return $newPath;
            }

            $path = $file->storeAs(
                $component->getDirectory(),
                $component->getUploadedFileNameForStorage($file),
                $component->getDiskName(),
            );

            if (! is_string($path) || $path === '') {
                return null;
            }

            if ($component->getVisibility() === 'public') {
                rescue(fn () => $component->getDisk()->setVisibility($path, 'public'), report: false);
            }

            ListingPhotoWatermark::applyToStoredPath($path, $component->getDiskName());
            ListingPhotoDerivative::ensureForStoredPath($path, $component->getDiskName());

            return $path;
        });
    }

    /**
     * @param  array<string> | Arrayable | Closure  $types
     */
    public function acceptedFileTypes(array | Arrayable | Closure $types): static
    {
        $this->acceptedFileTypes = $types;

        $this->rule(static fn (): string => 'mimes:'.implode(',', ListingPhotoUpload::extensions()));

        return $this;
    }
}
