<?php

namespace App\Filament\Admin\Resources\Promotions\Pages;

use App\Filament\Admin\Resources\Promotions\PromotionResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePromotion extends CreateRecord
{
    protected static string $resource = PromotionResource::class;

    protected ?string $heading = 'Нова акція';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        if (($data['slug'] ?? '') === '') {
            $data['slug'] = null;
        }

        return $data;
    }
}
