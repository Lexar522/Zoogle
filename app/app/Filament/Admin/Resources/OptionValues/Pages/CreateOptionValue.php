<?php

namespace App\Filament\Admin\Resources\OptionValues\Pages;

use App\Filament\Admin\Resources\OptionValues\OptionValueResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOptionValue extends CreateRecord
{
    protected static string $resource = OptionValueResource::class;
}
