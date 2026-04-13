<?php

namespace App\Filament\Admin\Resources\OptionGroups\Pages;

use App\Filament\Admin\Resources\OptionGroups\OptionGroupResource;
use App\Models\OptionGroup;
use App\Support\CatalogCategoryTree;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateOptionGroup extends CreateRecord
{
    protected static string $resource = OptionGroupResource::class;

    protected ?string $heading = 'Створити групу опцій';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        if (($data['slug'] ?? null) === 'category') {
            return $data;
        }

        if (($data['applies_mode'] ?? '') === 'category') {
            $root = (int) ($data['scope_category_level_1_id'] ?? 0);
            if ($root <= 0) {
                throw ValidationException::withMessages([
                    'scope_category_level_1_id' => 'Оберіть категорію (рівень 1).',
                ]);
            }
        }

        $data['product_type'] = OptionGroup::productTypeFromAppliesFormState($data);
        unset($data['applies_mode']);
        for ($i = 1; $i <= CatalogCategoryTree::MAX_DEPTH; $i++) {
            unset($data['scope_category_level_'.$i.'_id']);
        }

        return $data;
    }
}
