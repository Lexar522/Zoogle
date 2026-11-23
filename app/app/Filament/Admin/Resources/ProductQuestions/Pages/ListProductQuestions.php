<?php

namespace App\Filament\Admin\Resources\ProductQuestions\Pages;

use App\Filament\Admin\Resources\ProductQuestions\ProductQuestionResource;
use Filament\Resources\Pages\ListRecords;

class ListProductQuestions extends ListRecords
{
    protected static string $resource = ProductQuestionResource::class;

    protected ?string $heading = 'Питання покупців';

    protected ?string $subheading = 'Натисніть «Відповісти» — відповідь з\'явиться під питанням на сторінці товару, а покупець, який залишив email, отримає лист.';
}
