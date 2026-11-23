<?php

namespace App\Filament\Admin\Resources\Sponsorships\Pages;

use App\Filament\Admin\Concerns\WarnsWhenSponsorshipDisabled;
use App\Filament\Admin\Resources\Sponsorships\SponsorshipResource;
use Filament\Resources\Pages\ListRecords;

class ListSponsorships extends ListRecords
{
    use WarnsWhenSponsorshipDisabled;

    protected static string $resource = SponsorshipResource::class;

    protected ?string $heading = 'Опікуни';
}
