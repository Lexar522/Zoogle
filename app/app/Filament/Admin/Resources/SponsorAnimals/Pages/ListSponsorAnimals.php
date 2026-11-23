<?php

namespace App\Filament\Admin\Resources\SponsorAnimals\Pages;

use App\Filament\Admin\Concerns\WarnsWhenSponsorshipDisabled;
use App\Filament\Admin\Resources\SponsorAnimals\SponsorAnimalResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSponsorAnimals extends ListRecords
{
    use WarnsWhenSponsorshipDisabled;

    protected static string $resource = SponsorAnimalResource::class;

    protected ?string $heading = 'Тварини під опікою';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Додати тварину'),
        ];
    }
}
