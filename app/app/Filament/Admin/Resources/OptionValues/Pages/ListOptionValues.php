<?php

namespace App\Filament\Admin\Resources\OptionValues\Pages;

use App\Filament\Admin\Resources\OptionValues\OptionValueResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOptionValues extends ListRecords
{
    protected static string $resource = OptionValueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
