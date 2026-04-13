<?php

namespace App\Filament\Admin\Resources\Promotions\Pages;

use App\Filament\Admin\Resources\Promotions\PromotionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPromotion extends EditRecord
{
    protected static string $resource = PromotionResource::class;

    protected ?string $heading = 'Редагування акції';

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        if (($data['slug'] ?? '') === '') {
            $data['slug'] = null;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->label('Видалити'),
        ];
    }
}
