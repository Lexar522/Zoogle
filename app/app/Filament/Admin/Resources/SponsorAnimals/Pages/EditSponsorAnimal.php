<?php

namespace App\Filament\Admin\Resources\SponsorAnimals\Pages;

use App\Filament\Admin\Resources\SponsorAnimals\SponsorAnimalResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSponsorAnimal extends EditRecord
{
    protected static string $resource = SponsorAnimalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->modalDescription('Історія заявок і опікунів збережеться, але сторінка тварини зникне з сайту.'),
        ];
    }
}
