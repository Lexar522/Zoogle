<?php

namespace App\Filament\Admin\Resources\OptionGroups\Pages;

use App\Filament\Admin\Pages\ManageCatalogCategories;
use App\Filament\Admin\Resources\OptionGroups\OptionGroupResource;
use App\Models\OptionGroup;
use App\Support\CatalogCategoryTree;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Facades\FilamentView;
use Illuminate\Validation\ValidationException;

class EditOptionGroup extends EditRecord
{
    protected static string $resource = OptionGroupResource::class;

    protected ?string $heading = 'Змінити групу опцій';

    public function mount(int|string $record): void
    {
        $resolved = static::getResource()::resolveRecordRouteBinding($record);
        if ($resolved instanceof OptionGroup && $resolved->slug === 'category') {
            $url = ManageCatalogCategories::getUrl();
            $this->redirect($url, navigate: FilamentView::hasSpaMode($url));

            return;
        }

        parent::mount($record);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (($data['slug'] ?? null) === 'category') {
            return $data;
        }

        return array_merge(
            $data,
            OptionGroup::appliesFormStateFromProductType((string) ($data['product_type'] ?? OptionGroup::CATALOG_PRODUCT_TYPE))
        );
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Ensure toggle is always persisted as boolean.
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        // System category group stays active and is managed via values only.
        if (($this->record->slug ?? null) === 'category') {
            $data['is_active'] = true;

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

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->modalHeading(fn (): string => 'Видалити групу опцій: '.(string) ($this->record->name ?? ''))
                ->visible(fn (): bool => $this->record->slug !== 'category'),
        ];
    }
}
