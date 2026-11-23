<?php

namespace App\Filament\Admin\Resources\SponsorAnimals\Pages;

use App\Filament\Admin\Resources\SponsorAnimals\SponsorAnimalResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSponsorAnimal extends CreateRecord
{
    protected static string $resource = SponsorAnimalResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
