<?php

namespace App\Filament\Admin\Resources\CareArticles\Pages;

use App\Filament\Admin\Resources\CareArticles\CareArticleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCareArticles extends ListRecords
{
    protected static string $resource = CareArticleResource::class;

    protected ?string $heading = 'Статті';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Додати статтю'),
        ];
    }
}
