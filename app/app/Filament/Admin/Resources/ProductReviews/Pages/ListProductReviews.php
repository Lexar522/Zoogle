<?php

namespace App\Filament\Admin\Resources\ProductReviews\Pages;

use App\Filament\Admin\Resources\ProductReviews\ProductReviewResource;
use Filament\Resources\Pages\ListRecords;

class ListProductReviews extends ListRecords
{
    protected static string $resource = ProductReviewResource::class;

    protected ?string $heading = 'Відгуки';

    protected ?string $subheading = 'Нові відгуки з\'являються на сайті лише після «Опублікувати». Схвалені відгуки дають зірочки біля товару в Google.';
}
