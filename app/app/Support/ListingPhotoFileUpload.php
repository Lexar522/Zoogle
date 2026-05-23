<?php

namespace App\Support;

use Closure;
use Filament\Forms\Components\FileUpload;
use Illuminate\Contracts\Support\Arrayable;

/**
 * FileUpload для фото товарів: acceptedFileTypes для FilePond, але валідація через mimes,
 * не mimetypes (на shared-хостингу fileinfo часто віддає application/octet-stream для JPEG).
 */
class ListingPhotoFileUpload extends FileUpload
{
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
